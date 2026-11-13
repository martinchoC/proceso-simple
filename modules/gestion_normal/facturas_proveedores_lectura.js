/**
 * Facturas de proveedores - carga desde archivo (PDF / JPG / PNG).
 *
 * El navegador lee lo que puede sin servidor (pdf.js: texto y primera página como imagen; jsQR: el QR de ARCA;
 * tesseract.js: OCR de imágenes y PDF escaneados, solo si hace falta) y sube el archivo con eso. El servidor
 * (facturas_proveedores_ajax.php, acciones lectura_*) arma el resultado, lo valida y devuelve la propuesta; acá se
 * abre el formulario de siempre pre-cargado (fpPrecargarDesdeArchivo de facturas_proveedores.js) con el documento a
 * la izquierda, los campos dudosos resaltados y los errores con su motivo. Se guarda con "Guardar Factura" (Borrador).
 * Librerías locales, cargadas recién cuando se usan: assets/js/pdfjs-dist-3.11.174, jsqr-1.4.0, tesseract.js-5.1.1.
 */
(function ($) {
    'use strict';

    var AJAX = 'facturas_proveedores_ajax.php';
    var MAX_BYTES = 10 * 1024 * 1024;
    var TIPOS = ['application/pdf', 'image/jpeg', 'image/png'];
    // URLs absolutas: tesseract.js solo baja el idioma de una URL http(s):// y su worker resuelve las rutas desde su propia ubicación
    var A = new URL(window.APP_ASSETS_URL || '/', window.location.href).href;
    var LIBS = {
        pdf: A + 'js/pdfjs-dist-3.11.174/pdf.min.js',
        pdfWorker: A + 'js/pdfjs-dist-3.11.174/pdf.worker.min.js',
        qr: A + 'js/jsqr-1.4.0/jsQR.js',
        tesseract: A + 'js/tesseract.js-5.1.1/tesseract.min.js',
        tesseractWorker: A + 'js/tesseract.js-5.1.1/worker.min.js',
        tesseractCore: A + 'js/tesseract.js-core-5.1.1',
        tesseractLang: A + 'js/tesseract.js-data-spa-4.0.0'
    };
    var ETIQUETAS = {
        emisor_cuit: 'CUIT emisor', emisor_razon_social: 'Razón social', receptor_cuit: 'CUIT receptor',
        tipo_comprobante: 'Tipo (cód. ARCA)', letra: 'Letra', punto_venta: 'Punto de venta', numero: 'Número',
        fecha_emision: 'Fecha de emisión', moneda: 'Moneda', cotizacion: 'Cotización', neto_gravado: 'Neto gravado',
        subtotal: 'Subtotal', iva_21: 'IVA 21%', iva_10_5: 'IVA 10,5%', iva_27: 'IVA 27%', otros_iva: 'Otros IVA',
        no_gravado: 'No gravado', exento: 'Exento', percepciones_iibb: 'Percepciones IIBB', percepciones_iva: 'Percepción IVA',
        otros_tributos: 'Otros tributos', total: 'Total', cae: 'CAE', cae_vencimiento: 'Vto. CAE', condicion_pago: 'Condición de venta'
    };
    var IMPORTES = ['cotizacion', 'neto_gravado', 'subtotal', 'iva_21', 'iva_10_5', 'iva_27', 'otros_iva', 'no_gravado', 'exento',
                    'percepciones_iibb', 'percepciones_iva', 'otros_tributos', 'total'];
    // Campo leído -> control del formulario donde se resalta
    var CONTROLES = {
        emisor_cuit: '#entidad_combo', emisor_razon_social: '#entidad_combo', tipo_comprobante: '#comprobante_tipo_id',
        letra: '#comprobante_tipo_id', punto_venta: '#comprobante_pv', numero: '#comprobante_nro', fecha_emision: '#f_emision'
    };

    var estado = { archivo: null, urlLocal: null, propuesta: null };
    var scripts = {};

    // ---------------------------------------------------------------------------------------------
    // Utilidades
    // ---------------------------------------------------------------------------------------------

    function esc(t) {
        return $('<div>').text(t === null || t === undefined ? '' : String(t)).html();
    }

    function importe(n) {
        var v = parseFloat(n);
        return isNaN(v) ? esc(n) : '$ ' + v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function cargarScript(url) {
        if (!scripts[url]) {
            scripts[url] = new Promise(function (ok, mal) {
                var s = document.createElement('script');
                s.src = url;
                s.onload = ok;
                s.onerror = function () { mal(new Error('No se pudo cargar ' + url)); };
                document.head.appendChild(s);
            });
        }
        return scripts[url];
    }

    function progreso(texto, pct) {
        $('#flProgreso').removeClass('d-none');
        $('#flProgresoTexto').text(texto);
        if (pct !== undefined) $('#flProgresoBarra').css('width', pct + '%');
    }

    function post(datos) {
        return $.ajax({
            url: AJAX, type: 'POST', data: datos, dataType: 'json',
            processData: !(datos instanceof FormData), contentType: datos instanceof FormData ? false : 'application/x-www-form-urlencoded; charset=UTF-8',
            headers: { 'X-CSRF-Token': window.FP_CSRF || '' }
        });
    }

    function mensajeError(xhr, porDefecto) {
        var r = xhr && xhr.responseJSON;
        return (r && (r.message || r.error)) || porDefecto;
    }

    // ---------------------------------------------------------------------------------------------
    // Lectura en el navegador
    // ---------------------------------------------------------------------------------------------

    /** Texto de las primeras 3 páginas, con las líneas rearmadas por posición (aproxima pdftotext -layout). */
    function textoPdf(pdf) {
        var paginas = [];
        for (var n = 1; n <= Math.min(3, pdf.numPages); n++) paginas.push(n);
        return paginas.reduce(function (cadena, n) {
            return cadena.then(function (lineas) {
                return pdf.getPage(n).then(function (page) { return page.getTextContent(); }).then(function (tc) {
                    var filas = [];
                    tc.items.forEach(function (it) {
                        if (!it.str || !it.str.trim()) return;
                        var x = it.transform[4], y = it.transform[5];
                        var f = filas.find(function (f) { return Math.abs(f.y - y) < 3; });
                        if (!f) { f = { y: y, items: [] }; filas.push(f); }
                        f.items.push({ x: x, w: it.width || 0, s: it.str });
                    });
                    filas.sort(function (a, b) { return b.y - a.y; });
                    filas.forEach(function (f) {
                        f.items.sort(function (a, b) { return a.x - b.x; });
                        var linea = '', fin = null;
                        f.items.forEach(function (i) {
                            if (fin !== null) {
                                var hueco = i.x - fin;
                                linea += hueco > 12 ? '    ' : (hueco > 1.5 ? ' ' : '');
                            }
                            linea += i.s;
                            fin = i.x + i.w;
                        });
                        lineas.push(linea);
                    });
                    lineas.push('');
                    return lineas;
                });
            });
        }, Promise.resolve([])).then(function (lineas) { return lineas.join('\n'); });
    }

    /** Primera página del PDF como canvas de ~1800 px de ancho. */
    function paginaPdfCanvas(pdf) {
        return pdf.getPage(1).then(function (page) {
            var v1 = page.getViewport({ scale: 1 });
            var viewport = page.getViewport({ scale: Math.min(4, 1800 / v1.width) });
            var canvas = document.createElement('canvas');
            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            var ctx = canvas.getContext('2d', { willReadFrequently: true });
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            return page.render({ canvasContext: ctx, viewport: viewport }).promise.then(function () { return canvas; });
        });
    }

    function imagenCanvas(archivo) {
        return new Promise(function (ok, mal) {
            var img = new Image();
            var url = URL.createObjectURL(archivo);
            img.onload = function () {
                var escala = Math.min(1, 2400 / Math.max(img.naturalWidth, img.naturalHeight));
                var canvas = document.createElement('canvas');
                canvas.width = Math.round(img.naturalWidth * escala);
                canvas.height = Math.round(img.naturalHeight * escala);
                canvas.getContext('2d', { willReadFrequently: true }).drawImage(img, 0, 0, canvas.width, canvas.height);
                URL.revokeObjectURL(url);
                ok(canvas);
            };
            img.onerror = function () { URL.revokeObjectURL(url); mal(new Error('No se pudo abrir la imagen.')); };
            img.src = url;
        });
    }

    /** QR de ARCA en la imagen: entera y por cuadrantes (el QR suele ir abajo a la izquierda). */
    function buscarQr(canvas) {
        if (typeof window.jsQR !== 'function') return null;
        var ctx = canvas.getContext('2d', { willReadFrequently: true });
        var zonas = [[0, 0, 1, 1], [0, 0.5, 0.5, 0.5], [0, 0.5, 1, 0.5], [0.5, 0.5, 0.5, 0.5], [0, 0, 0.5, 0.5], [0.5, 0, 0.5, 0.5]];
        for (var i = 0; i < zonas.length; i++) {
            var z = zonas[i];
            var x = Math.floor(z[0] * canvas.width), y = Math.floor(z[1] * canvas.height);
            var w = Math.floor(z[2] * canvas.width), h = Math.floor(z[3] * canvas.height);
            try {
                var datos = ctx.getImageData(x, y, w, h);
                var r = window.jsQR(datos.data, w, h, { inversionAttempts: 'attemptBoth' });
                if (r && /(afip|arca)\.gob\.ar/i.test(r.data)) return r.data;
            } catch (e) { /* zona fuera de rango */ }
        }
        return null;
    }

    /** Escala de grises y contraste (estira el histograma) antes del OCR. */
    function preprocesar(canvas) {
        var c = document.createElement('canvas');
        c.width = canvas.width;
        c.height = canvas.height;
        var ctx = c.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(canvas, 0, 0);
        var img = ctx.getImageData(0, 0, c.width, c.height);
        var d = img.data, min = 255, max = 0, i, g;
        for (i = 0; i < d.length; i += 4) {
            g = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
            d[i] = g;
            if (g < min) min = g;
            if (g > max) max = g;
        }
        var rango = Math.max(1, max - min);
        for (i = 0; i < d.length; i += 4) {
            g = (d[i] - min) * 255 / rango;
            d[i] = d[i + 1] = d[i + 2] = g;
        }
        ctx.putImageData(img, 0, 0);
        return c;
    }

    function ocr(canvas) {
        return cargarScript(LIBS.tesseract).then(function () {
            return window.Tesseract.createWorker('spa', 1, {
                workerPath: LIBS.tesseractWorker, corePath: LIBS.tesseractCore, langPath: LIBS.tesseractLang, gzip: false,
                logger: function (m) {
                    if (m.status === 'recognizing text') progreso('Leyendo la imagen (OCR)… ' + Math.round(m.progress * 100) + '%', 40 + m.progress * 40);
                    else if (m.status) progreso('Preparando el OCR…', 35);
                }
            });
        }).then(function (worker) {
            return worker.recognize(preprocesar(canvas)).then(function (res) {
                worker.terminate();
                return { texto: res.data.text || '', confianza: res.data.confidence };
            }, function (e) { worker.terminate(); throw e; });
        });
    }

    /** Lo que se puede leer en el navegador: { qr, texto, ocr, ocr_confianza }. Nunca falla: lo que no sale queda vacío. */
    function leerEnNavegador(archivo) {
        var cliente = { qr: '', texto: '', ocr: '', ocr_confianza: null };
        var canvasP;
        if (archivo.type === 'application/pdf') {
            progreso('Leyendo el PDF…', 10);
            canvasP = cargarScript(LIBS.pdf).then(function () {
                window.pdfjsLib.GlobalWorkerOptions.workerSrc = LIBS.pdfWorker;
                return archivo.arrayBuffer();
            }).then(function (buf) {
                return window.pdfjsLib.getDocument({ data: new Uint8Array(buf) }).promise;
            }).then(function (pdf) {
                return textoPdf(pdf).then(function (t) { cliente.texto = t; return paginaPdfCanvas(pdf); });
            });
        } else {
            progreso('Abriendo la imagen…', 10);
            canvasP = imagenCanvas(archivo);
        }
        return canvasP.then(function (canvas) {
            progreso('Buscando el QR de ARCA…', 25);
            return cargarScript(LIBS.qr).then(function () {
                cliente.qr = buscarQr(canvas) || '';
                var sinTexto = cliente.texto.replace(/\s/g, '').length < 40;
                if (!sinTexto) return cliente;
                return ocr(canvas).then(function (r) {
                    cliente.ocr = r.texto;
                    cliente.ocr_confianza = r.confianza;
                    return cliente;
                }, function (e) {
                    console.warn('OCR en el navegador:', e);
                    return cliente;
                });
            });
        }).catch(function (e) {
            console.warn('Lectura en el navegador:', e);
            return cliente;
        });
    }

    // ---------------------------------------------------------------------------------------------
    // Subida y propuesta
    // ---------------------------------------------------------------------------------------------

    function procesarArchivo(archivo) {
        if (!archivo) return;
        if (TIPOS.indexOf(archivo.type) < 0) {
            Swal.fire({ icon: 'warning', title: 'Revisá los datos', text: 'El archivo tiene que ser PDF, JPG o PNG.' });
            return;
        }
        if (archivo.size > MAX_BYTES) {
            Swal.fire({ icon: 'warning', title: 'Revisá los datos', text: 'El archivo no puede superar 10 MB.' });
            return;
        }
        $('#flZona').addClass('d-none');
        leerEnNavegador(archivo).then(function (cliente) {
            progreso('Interpretando los datos…', 85);
            var fd = new FormData();
            fd.append('accion', 'lectura_procesar');
            fd.append('archivo', archivo);
            fd.append('cliente', JSON.stringify(cliente));
            if ($('#flForzarIa').is(':checked')) fd.append('forzar_ia', '1');
            return post(fd);
        }).then(function (res) {
            if (!res || !res.success) throw { message: (res && res.message) || 'No se pudo leer el archivo.' };
            estado.archivo = archivo;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalLecturaArchivo')).hide();
            aplicarPropuesta(res);
        }).catch(function (e) {
            // jqXHR (error HTTP) u objeto con message (respuesta success = false)
            var msg = e && e.responseJSON ? mensajeError(e, 'No se pudo leer el archivo.') : ((e && e.message) || 'No se pudo leer el archivo.');
            reiniciarModalSubida();
            Swal.fire({ icon: 'error', title: 'No se pudo completar', text: msg });
        });
    }

    function reiniciarModalSubida() {
        $('#flArchivo').val('');
        $('#flZona').removeClass('d-none fl-arrastrando');
        $('#flProgreso').addClass('d-none');
        $('#flProgresoBarra').css('width', '0%');
        $('#flForzarIa').prop('checked', false);
    }

    /** Abre el formulario con la propuesta y arma el panel del documento. */
    function aplicarPropuesta(p) {
        estado.propuesta = p;
        var urlLocal = estado.archivo ? URL.createObjectURL(estado.archivo) : null;
        window.fpPrecargarDesdeArchivo(p.form, function () {
            window.fpLecturaAdjuntoId = p.adjunto_id;
            if (estado.urlLocal && estado.urlLocal !== urlLocal) URL.revokeObjectURL(estado.urlLocal);
            estado.urlLocal = urlLocal;
            mostrarPanel(p);
            marcarCampos(p);
            if (p.ia && p.ia.error) {
                Swal.fire({ icon: 'warning', title: 'Revisá los datos', text: p.ia.error });
            }
        });
    }

    function urlArchivo(adjuntoId, descargar) {
        return AJAX + '?accion=lectura_archivo&adjunto_id=' + encodeURIComponent(adjuntoId) + (descargar ? '&descargar=1' : '');
    }

    function mostrarVisor(url, mime) {
        var $v = $('#flVisor').empty();
        if (mime === 'application/pdf') {
            $('<iframe>', { src: url, title: 'Documento' }).appendTo($v);
        } else {
            $('<img>', { src: url, alt: 'Documento' }).appendTo($v);
        }
    }

    function mostrarPanel(p) {
        var mime = estado.archivo ? estado.archivo.type : '';
        mostrarVisor(estado.urlLocal || urlArchivo(p.adjunto_id), mime);
        $('#btnFlAbrirArchivo').attr('href', urlArchivo(p.adjunto_id)).removeClass('d-none');
        $('#btnFlReintentarIa').toggleClass('d-none', !(p.ia && (p.ia.disponible || p.ia.usada)))
            .prop('disabled', !(p.ia && p.ia.disponible))
            .attr('title', p.ia && !p.ia.disponible ? (p.ia.motivo || 'No disponible') : 'Volver a leer el archivo con IA');
        $('#flCapas').html((p.capas || []).map(function (c) {
            return '<span class="badge text-bg-primary fl-capa">' + esc(c) + '</span>';
        }).join('') || '<span class="badge text-bg-secondary fl-capa">Sin datos leídos</span>');

        var h = '';
        if (p.listo) {
            h += '<div class="alert alert-success py-1 px-2 mb-2"><i class="fas fa-check-circle me-1"></i>Los datos cierran. Revíselos y guarde.</div>';
        } else {
            h += '<div class="alert alert-warning py-1 px-2 mb-2"><i class="fas fa-exclamation-triangle me-1"></i>Hay datos para revisar antes de guardar.</div>';
        }
        if (p.duplicado) {
            h += '<div class="alert alert-danger py-1 px-2 mb-2">Este comprobante ya está cargado para el proveedor. Si lo guarda, el sistema lo va a rechazar.</div>';
        }
        if (p.proveedor_nuevo) {
            h += '<div class="alert alert-info py-1 px-2 mb-2">El proveedor <strong>' + esc(p.proveedor_nuevo.razon_social || '') + '</strong> (CUIT '
               + esc(p.proveedor_nuevo.cuit) + ') no está cargado. '
               + '<button type="button" class="btn btn-sm btn-primary ms-1" id="btnFlCrearProveedor"><i class="fas fa-user-plus me-1"></i>Darlo de alta</button></div>';
        }
        var errores = p.errores || {};
        // Los errores de campos del formulario se ven junto al campo; el resto (importes, CAE…), acá
        var conError = Object.keys(errores).filter(function (k) { return !CONTROLES[k] || (k === 'emisor_cuit' && p.proveedor_nuevo); });
        if (conError.length) {
            h += '<ul class="text-danger ps-3 mb-2">' + conError.map(function (k) {
                return '<li><strong>' + esc(ETIQUETAS[k] || k) + ':</strong> ' + esc(errores[k]) + '</li>';
            }).join('') + '</ul>';
        }
        if ((p.avisos || []).length) {
            h += '<ul class="text-body-secondary ps-3 mb-2">' + p.avisos.map(function (a) { return '<li>' + esc(a) + '</li>'; }).join('') + '</ul>';
        }

        // Percepciones sin asignar: el usuario elige la configuración
        (p.percepciones_pendientes || []).forEach(function (pp, i) {
            h += '<div class="border rounded p-2 mb-2 fl-pendiente" data-idx="' + i + '">'
               + '<div class="mb-1"><strong>Percepción ' + esc(pp.tipo) + '</strong> ' + importe(pp.importe)
               + (pp.texto ? ' <span class="text-muted">(' + esc(pp.texto) + ')</span>' : '') + '<div class="fl-motivo">' + esc(pp.motivo) + '</div></div>';
            if ((pp.opciones || []).length) {
                h += '<div class="d-flex gap-1"><select class="form-select form-select-sm fl-pendiente-config"><option value="">Elegir…</option>'
                   + pp.opciones.map(function (o) { return '<option value="' + esc(o.empresa_impuesto_config_id) + '">' + esc(o.nombre) + '</option>'; }).join('')
                   + '</select><button type="button" class="btn btn-sm btn-outline-primary fl-pendiente-aplicar">Agregar</button></div>';
            }
            h += '</div>';
        });

        // Campos leídos: valor, confianza y capa
        var campos = p.campos || {};
        var filas = Object.keys(ETIQUETAS).filter(function (k) { return campos[k]; }).map(function (k) {
            var c = campos[k];
            var valor = IMPORTES.indexOf(k) >= 0 ? importe(c.valor) : esc(c.valor);
            var err = errores[k] ? '<div class="fl-motivo">' + esc(errores[k]) + '</div>' : '';
            return '<tr><td class="text-muted">' + esc(ETIQUETAS[k]) + '</td><td>' + valor + err + '</td>'
                 + '<td class="fl-conf-' + esc(c.confianza) + '" title="Confianza">' + esc(c.confianza) + '</td>'
                 + '<td><span class="badge text-bg-light border fl-capa">' + esc(c.capa) + '</span></td></tr>';
        });
        if (filas.length) {
            h += '<details open class="mb-2"><summary class="fw-medium">Datos leídos</summary><table class="table table-sm mb-0"><tbody>' + filas.join('') + '</tbody></table></details>';
        }

        // Ítems leídos (referencia; los que tienen producto se cargaron como línea)
        if ((p.items_referencia || []).length) {
            h += '<details class="mb-2"><summary class="fw-medium">Ítems del comprobante (' + p.items_referencia.length + ')</summary>'
               + '<table class="table table-sm mb-0"><thead><tr><th>Código</th><th>Descripción</th><th class="text-end">Cant.</th><th class="text-end">Subtotal</th></tr></thead><tbody>'
               + p.items_referencia.map(function (it) {
                   return '<tr><td>' + esc(it.codigo) + '</td><td>' + esc(it.descripcion)
                        + (it.producto_id ? ' <span class="badge text-bg-success fl-capa" title="' + esc(it.producto_nombre) + '">cargado</span>' : '')
                        + '</td><td class="text-end">' + esc(it.cantidad) + '</td><td class="text-end">' + importe(it.subtotal) + '</td></tr>';
               }).join('') + '</tbody></table></details>';
        }
        if (p.ia && p.ia.habilitada) {
            h += '<div class="small text-muted">Lecturas con IA hoy: ' + esc(p.ia.usos_hoy) + ' de ' + esc(p.ia.tope) + '.</div>';
        }
        $('#flResumen').html(h);
        $('#panelLecturaArchivo').removeClass('d-none');
    }

    function marcarCampos(p) {
        limpiarMarcas();
        var campos = p.campos || {}, errores = p.errores || {};
        Object.keys(CONTROLES).forEach(function (k) {
            var $c = $(CONTROLES[k]);
            if (!$c.length) return;
            if (errores[k]) {
                $c.addClass('fl-campo-error');
                if (!$c.next('.fl-motivo').length) $('<div class="fl-motivo fl-marca"></div>').text(errores[k]).insertAfter($c);
            } else if (campos[k] && (campos[k].confianza === 'media' || campos[k].confianza === 'baja')) {
                $c.addClass('fl-campo-' + campos[k].confianza);
            }
        });
        if (!p.form.entidad_id) $('#entidad_combo').addClass('fl-campo-error');
        // Revisado a mano: deja de resaltarse
        $.each(CONTROLES, function (k, sel) {
            $(sel).one('change.fl input.fl', function () {
                $(this).removeClass('fl-campo-media fl-campo-baja fl-campo-error').next('.fl-marca').remove();
            });
        });
    }

    function limpiarMarcas() {
        $.each(CONTROLES, function (k, sel) {
            $(sel).removeClass('fl-campo-media fl-campo-baja fl-campo-error').off('.fl');
        });
        $('.fl-marca').remove();
    }

    // Llamada desde resetModal(): nueva factura, edición, etc.
    window.fpLecturaLimpiar = function () {
        window.fpLecturaAdjuntoId = null;
        window.fpLecturaEnCurso = false;
        limpiarMarcas();
        $('#panelLecturaArchivo').addClass('d-none');
        $('#flVisor').empty();
        $('#flResumen').empty();
        $('#flCapas').empty();
        if (estado.urlLocal) URL.revokeObjectURL(estado.urlLocal);
        estado.urlLocal = null;
    };

    // ---------------------------------------------------------------------------------------------
    // Eventos
    // ---------------------------------------------------------------------------------------------

    $('#btnCargarArchivo').on('click', function () {
        reiniciarModalSubida();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalLecturaArchivo')).show();
    });

    $('#flArchivo').on('change', function () {
        procesarArchivo(this.files && this.files[0]);
    });

    $('#flZona').on('dragenter dragover', function (e) {
        e.preventDefault();
        $(this).addClass('fl-arrastrando');
    }).on('dragleave', function () {
        $(this).removeClass('fl-arrastrando');
    }).on('drop', function (e) {
        e.preventDefault();
        $(this).removeClass('fl-arrastrando');
        var dt = e.originalEvent.dataTransfer;
        procesarArchivo(dt && dt.files && dt.files[0]);
    });

    $('#btnFlOcultarPanel').on('click', function () {
        $('#panelLecturaArchivo').addClass('d-none');
    });

    $('#btnFlReintentarIa').on('click', function () {
        var p = estado.propuesta;
        if (!p) return;
        Swal.fire({
            icon: 'question', title: 'Leer de nuevo con IA',
            text: 'El archivo se envía al servicio de IA y el formulario se vuelve a cargar con lo leído (se pierden los cambios hechos a mano).',
            showCancelButton: true, confirmButtonText: 'Leer con IA', cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            var $b = $('#btnFlReintentarIa').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Leyendo…');
            post({ accion: 'lectura_reintentar_ia', adjunto_id: p.adjunto_id }).then(function (res) {
                if (!res || !res.success) throw { message: (res && res.message) || 'No se pudo leer con IA.' };
                aplicarPropuesta(res);
            }).catch(function (e) {
                Swal.fire({ icon: 'error', title: 'No se pudo completar', text: e.responseJSON ? mensajeError(e, 'No se pudo leer con IA.') : (e.message || 'No se pudo leer con IA.') });
            }).then(function () {
                $b.prop('disabled', false).html('<i class="fas fa-magic me-1"></i>Reintentar con IA');
            });
        });
    });

    $(document).on('click', '#btnFlCrearProveedor', function () {
        var p = estado.propuesta;
        if (!p || !p.proveedor_nuevo) return;
        Swal.fire({
            icon: 'question', title: 'Dar de alta el proveedor',
            html: 'Se crea <strong>' + esc(p.proveedor_nuevo.razon_social || 'CUIT ' + p.proveedor_nuevo.cuit) + '</strong> (CUIT '
                + esc(p.proveedor_nuevo.cuit) + ') con la condición de pago por defecto de la empresa. Después puede completar sus datos en Proveedores.',
            showCancelButton: true, confirmButtonText: 'Dar de alta', cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            post({ accion: 'lectura_crear_proveedor', adjunto_id: p.adjunto_id }).then(function (res) {
                if (!res || !res.success) throw { message: (res && res.message) || 'No se pudo dar de alta el proveedor.' };
                aplicarPropuesta(res);
            }).catch(function (e) {
                Swal.fire({ icon: 'error', title: 'No se pudo completar', text: e.responseJSON ? mensajeError(e, 'No se pudo dar de alta el proveedor.') : (e.message || 'No se pudo dar de alta el proveedor.') });
            });
        });
    });

    // Percepción pendiente: arma la fila igual que cargarImpuestosPorDefecto con la configuración elegida
    $(document).on('click', '.fl-pendiente-aplicar', function () {
        var $box = $(this).closest('.fl-pendiente');
        var pp = (estado.propuesta.percepciones_pendientes || [])[parseInt($box.data('idx'), 10)];
        var id = $box.find('.fl-pendiente-config').val();
        var cfg = (window.impuestosConfig || []).find(function (c) { return String(c.empresa_impuesto_config_id) === String(id); });
        if (!pp || !cfg) {
            Swal.fire({ icon: 'warning', title: 'Revisá los datos', text: 'Elegí la percepción.' });
            return;
        }
        var base = parseFloat($('#subtotal').val()) || 0;
        window.fpAgregarImpuestoLectura({
            empresa_impuesto_config_id: cfg.empresa_impuesto_config_id, impuesto_tipo_id: cfg.impuesto_tipo_id,
            impuesto_tipo: cfg.impuesto_tipo,
            impuesto_tipo_descripcion: cfg.impuesto_tipo + (cfg.jurisdiccion_nombre ? ' - ' + cfg.jurisdiccion_nombre : ''),
            impuesto_codigo_afip: cfg.codigo_afip, jurisdiccion_id: cfg.jurisdiccion_id,
            jurisdiccion_nombre: cfg.jurisdiccion_nombre || '', jurisdiccion_codigo: cfg.jurisdiccion_codigo || '',
            condicion_fiscal_id: cfg.condicion_fiscal_id, condicion_fiscal: cfg.condicion_fiscal,
            condicion_fiscal_codigo: cfg.condicion_fiscal_codigo, base_calculo: cfg.base_calculo || 'NETO_GRAVADO',
            base_imponible: base, alicuota: base > 0 ? Math.round(pp.importe / base * 1000000) / 10000 : 0,
            minimo_imponible: 0, monto_fijo: 0, importe: pp.importe, aplica_siempre: 0, prioridad: cfg.prioridad || 1,
            tipo_calculo: 'manual', detalle_origen: 'Leída del comprobante', tipo_origen: 'manual'
        });
        $box.replaceWith('<div class="text-success small mb-2"><i class="fas fa-check me-1"></i>Percepción ' + esc(pp.tipo) + ' '
                         + importe(pp.importe) + ' agregada (' + esc(cfg.impuesto_tipo + (cfg.jurisdiccion_nombre ? ' - ' + cfg.jurisdiccion_nombre : '')) + ').</div>');
    });

    // Factura existente con archivo original: se muestra en el panel (auditoría)
    $('#modalFacturaProveedor').on('shown.bs.modal', function () {
        var id = $('#factura_proveedor_id').val();
        if (!id || window.fpLecturaAdjuntoId) return;
        $.getJSON(AJAX, { accion: 'lectura_adjuntos_factura', factura_proveedor_id: id }, function (res) {
            var lista = (res && res.data) || [];
            if (!lista.length || String($('#factura_proveedor_id').val()) !== String(id)) return;
            var a = lista[lista.length - 1];
            mostrarVisor(urlArchivo(a.adjunto_id), a.mime);
            $('#btnFlAbrirArchivo').attr('href', urlArchivo(a.adjunto_id));
            $('#btnFlReintentarIa').addClass('d-none');
            $('#flCapas').html('<span class="badge text-bg-secondary fl-capa">Archivo original</span>');
            $('#flResumen').html(lista.map(function (x) {
                return '<div><a href="' + esc(urlArchivo(x.adjunto_id, true)) + '"><i class="fas fa-paperclip me-1"></i>' + esc(x.nombre_original)
                     + '</a> <span class="text-muted">' + esc(x.fecha_alta) + '</span></div>';
            }).join(''));
            $('#panelLecturaArchivo').removeClass('d-none');
        });
    });
})(jQuery);
