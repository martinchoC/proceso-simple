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
        otros_tributos: 'Otros tributos', descuento_general: 'Descuento general', descuento_general_pct: 'Descuento general %',
        total: 'Total', cae: 'CAE', cae_vencimiento: 'Vto. CAE', condicion_pago: 'Condición de venta'
    };
    var IMPORTES = ['cotizacion', 'neto_gravado', 'subtotal', 'iva_21', 'iva_10_5', 'iva_27', 'otros_iva', 'no_gravado', 'exento',
                    'percepciones_iibb', 'percepciones_iva', 'otros_tributos', 'descuento_general', 'total'];
    // Campo leído -> control del formulario donde se resalta
    var CONTROLES = {
        emisor_cuit: '#entidad_combo', emisor_razon_social: '#entidad_combo', tipo_comprobante: '#comprobante_tipo_id',
        letra: '#comprobante_tipo_id', punto_venta: '#comprobante_pv', numero: '#comprobante_nro', fecha_emision: '#f_emision'
    };

    // lote: [{archivo, estado: pendiente|leyendo|listo|revisar|error|guardada, mensaje, propuesta, facturaId}]
    // loteIdx: fila del lote abierta en el formulario ("Revisar"); null = carga de un solo archivo
    var estado = { archivo: null, urlLocal: null, propuesta: null, lote: [], loteIdx: null, procesando: false, desdeBandeja: false, guardadaBandeja: false };
    var scripts = {};

    // ---------------------------------------------------------------------------------------------
    // Utilidades
    // ---------------------------------------------------------------------------------------------

    function esc(t) {
        return $('<div>').text(t === null || t === undefined ? '' : String(t)).html();
    }

    function importe(n) {
        var v = parseFloat(n);
        return isNaN(v) ? esc(n) : '$ ' + Fmt.numero(v, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

    /** Motivo por el que el archivo no se acepta (tipo o tamaño), o null. */
    function archivoInvalido(archivo) {
        if (TIPOS.indexOf(archivo.type) < 0) return 'El archivo tiene que ser PDF, JPG o PNG.';
        if (archivo.size > MAX_BYTES) return 'El archivo no puede superar 10 MB.';
        return null;
    }

    function textoError(e, porDefecto) {
        // jqXHR (error HTTP) u objeto con message (respuesta success = false)
        return e && e.responseJSON ? mensajeError(e, porDefecto) : ((e && e.message) || porDefecto);
    }

    /** Sube el archivo y lo lee directamente con IA en el servidor (rechaza si success = false). */
    function leerYSubir(archivo) {
        progreso('Leyendo el archivo con IA…', 50);
        var fd = new FormData();
        fd.append('accion', 'lectura_procesar');
        fd.append('archivo', archivo);
        fd.append('forzar_ia', '1');
        return post(fd).then(function (res) {
            if (!res || !res.success) throw { message: (res && res.message) || 'No se pudo leer el archivo.' };
            return res;
        });
    }

    function procesarArchivo(archivo) {
        if (!archivo) return;
        var invalido = archivoInvalido(archivo);
        if (invalido) {
            Swal.fire({ icon: 'warning', title: 'Revisá los datos', text: invalido });
            return;
        }
        $('#flElegir').addClass('d-none');
        leerYSubir(archivo).then(function (res) {
            estado.archivo = archivo;
            estado.loteIdx = null;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalLecturaArchivo')).hide();
            aplicarPropuesta(res);
        }).catch(function (e) {
            reiniciarModalSubida();
            Swal.fire({ icon: 'error', title: 'No se pudo completar', text: textoError(e, 'No se pudo leer el archivo.') });
        });
    }

    /** Con un lote en curso o terminado se ve la tabla; si no, la zona para elegir archivos. */
    function reiniciarModalSubida() {
        $('#flArchivo, #flCarpeta').val('');
        $('#flZona').removeClass('fl-arrastrando');
        $('#flProgreso').addClass('d-none');
        $('#flProgresoBarra').css('width', '0%');
        if (!estado.lote.length) $('#flForzarIa').prop('checked', true);
        $('#flElegir').toggleClass('d-none', estado.lote.length > 0);
        $('#flLote').toggleClass('d-none', estado.lote.length === 0);
    }

    // ---------------------------------------------------------------------------------------------
    // Varios archivos (o una carpeta): se leen de a uno; los listos se guardan juntos en Borrador y
    // los que hay que revisar se abren en el formulario de a uno ("Revisar").
    // ---------------------------------------------------------------------------------------------

    function iniciarArchivos(lista) {
        var archivos = Array.prototype.slice.call(lista || []).filter(function (f) { return f && f.name && f.name.charAt(0) !== '.'; });
        if (!archivos.length) return;
        if (archivos.length === 1 && !estado.lote.length) {
            procesarArchivo(archivos[0]);
            return;
        }
        if (estado.procesando) {
            Swal.fire({ icon: 'warning', title: 'Revisá los datos', text: 'Esperá a que termine la lectura en curso.' });
            return;
        }
        archivos.sort(function (a, b) { return a.name.localeCompare(b.name); });
        archivos.forEach(function (f) {
            var invalido = archivoInvalido(f);
            estado.lote.push({ archivo: f, estado: invalido ? 'error' : 'pendiente', mensaje: invalido, propuesta: null, facturaId: null });
        });
        reiniciarModalSubida();
        cargarSucursalesLote();
        renderLote();
        leerLote();
    }

    function leerLote() {
        var i = estado.lote.findIndex(function (f) { return f.estado === 'pendiente'; });
        if (i < 0) {
            estado.procesando = false;
            $('#flProgreso').addClass('d-none');
            renderLote();
            return;
        }
        estado.procesando = true;
        var fila = estado.lote[i];
        fila.estado = 'leyendo';
        renderLote();
        var total = estado.lote.length;
        progreso('Archivo ' + (i + 1) + ' de ' + total + ': ' + fila.archivo.name, Math.round(i * 100 / total));
        leerYSubir(fila.archivo).then(function (res) {
            fila.propuesta = res;
            fila.estado = res.listo ? 'listo' : 'revisar';
        }).catch(function (e) {
            fila.estado = 'error';
            fila.mensaje = textoError(e, 'No se pudo leer el archivo.');
        }).then(function () {
            renderLote();
            leerLote();
        });
    }

    function cargarSucursalesLote() {
        if ($('#flLoteSucursal option').length) return;
        $.getJSON(AJAX, { accion: 'obtener_sucursales_empresa' }, function (data) {
            $('#flLoteSucursal').html((data || []).map(function (s) {
                return '<option value="' + esc(s.sucursal_id) + '">' + esc(s.sucursal_nombre) + '</option>';
            }).join(''));
        });
    }

    var ESTADOS_LOTE = {
        pendiente: '<span class="badge text-bg-light border">En espera</span>',
        leyendo: '<span class="badge text-bg-info"><span class="spinner-border spinner-border-sm me-1"></span>Leyendo</span>',
        listo: '<span class="badge text-bg-success">Listo</span>',
        revisar: '<span class="badge text-bg-warning">Revisar</span>',
        error: '<span class="badge text-bg-danger">No se pudo leer</span>',
        guardada: '<span class="badge text-bg-secondary">Guardada en Borrador</span>'
    };

    function renderLote() {
        var cuenta = { listo: 0, revisar: 0, error: 0, guardada: 0 };
        $('#flLoteFilas').html(estado.lote.map(function (f, i) {
            if (cuenta[f.estado] !== undefined) cuenta[f.estado]++;
            var p = f.propuesta, form = p ? p.form : null;
            var comp = form && form.comprobante_nro ? esc(form.comprobante_tipo_nombre || '') + ' ' + esc(form.comprobante_pv) + '-' + esc(form.comprobante_nro) : '';
            var prov = form ? esc(form.proveedor_nombre || (p.proveedor_nuevo ? p.proveedor_nuevo.razon_social + ' (nuevo)' : '')) : '';
            var total = p && p.campos && p.campos.total ? importe(p.campos.total.valor) : '';
            var detalle = '';
            if (f.mensaje) detalle = '<div class="fl-motivo">' + esc(f.mensaje) + '</div>';
            else if (f.estado === 'revisar' && p) {
                var errores = Object.keys(p.errores || {}).map(function (k) { return (ETIQUETAS[k] || k) + ': ' + p.errores[k]; });
                (p.percepciones_pendientes || []).forEach(function (pp) { errores.push('Percepción ' + pp.tipo + ': ' + pp.motivo); });
                detalle = '<div class="fl-motivo">' + esc(errores.slice(0, 2).join(' · ')) + (errores.length > 2 ? ' …' : '') + '</div>';
            }
            if (f.estado === 'guardada' && f.facturaId) detalle = '<div class="small text-muted">Factura ' + esc(f.facturaId) + '</div>';
            var boton = (f.estado === 'listo' || f.estado === 'revisar')
                ? '<button type="button" class="btn btn-sm btn-outline-primary fl-lote-revisar" data-idx="' + i + '">Revisar</button>' : '';
            return '<tr><td class="text-break">' + esc(f.archivo.name) + '</td><td>' + comp + '</td><td>' + prov + '</td>'
                 + '<td class="text-end">' + total + '</td><td>' + (ESTADOS_LOTE[f.estado] || '') + detalle + '</td><td>' + boton + '</td></tr>';
        }).join(''));
        $('#flLoteResumen').text(estado.lote.length + ' archivo(s): ' + cuenta.listo + ' listo(s), ' + cuenta.revisar + ' para revisar, '
                                 + cuenta.error + ' sin leer, ' + cuenta.guardada + ' guardado(s)');
        $('#btnFlGuardarListos').prop('disabled', estado.procesando || cuenta.listo === 0);
    }

    // Guardada desde el formulario (facturas_proveedores.js, al guardar con lectura_adjunto_id)
    window.fpLecturaGuardada = function (facturaId) {
        var f = estado.loteIdx !== null ? estado.lote[estado.loteIdx] : null;
        if (f) {
            f.estado = 'guardada';
            f.facturaId = facturaId;
            renderLote();
        }
        if (estado.desdeBandeja) {
            estado.guardadaBandeja = true;
            if (window.FP_EMBED && window.parent && window.parent !== window) {
                window.parent.postMessage({ tipo: 'bdj_factura_guardada', factura_proveedor_id: facturaId }, window.location.origin);
            } else {
                setTimeout(function () { window.location.href = 'bandeja_documentos.php'; }, 800);
            }
        }
    };

    /** Archivos de una carpeta arrastrada (y sus subcarpetas). */
    function leerEntradas(items) {
        var archivos = [];
        function recorrer(entrada) {
            if (!entrada) return Promise.resolve();
            if (entrada.isFile) {
                return new Promise(function (ok) { entrada.file(function (f) { archivos.push(f); ok(); }, function () { ok(); }); });
            }
            if (entrada.isDirectory) {
                var lector = entrada.createReader();
                return new Promise(function (ok) {
                    var todas = [];
                    (function leer() {   // readEntries devuelve de a tandas hasta una vacía
                        lector.readEntries(function (tanda) {
                            if (!tanda.length) { Promise.all(todas.map(recorrer)).then(ok); return; }
                            todas = todas.concat(Array.prototype.slice.call(tanda));
                            leer();
                        }, function () { ok(); });
                    })();
                });
            }
            return Promise.resolve();
        }
        var entradas = Array.prototype.slice.call(items).map(function (it) { return it.webkitGetAsEntry ? it.webkitGetAsEntry() : null; });
        return Promise.all(entradas.map(recorrer)).then(function () { return archivos; });
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
        var mime = estado.archivo ? estado.archivo.type : (p.mime || '');
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

        // Producto por producto o una línea por alícuota: preferencia del proveedor (servicios: teléfono, gas, luz)
        if (p.form && p.form.entidad_id) {
            var cp = p.condicion_proveedor || {};
            var modalMap = {
                'EN_FACTURA_GLOBAL': 'En Factura (Global)',
                'EN_FACTURA_ITEM': 'En Factura (Por Ítem)',
                'NOTA_CREDITO': 'Por NC Posterior',
                'SIN_DESCUENTO': 'Sin Descuento'
            };
            var modalTxt = modalMap[cp.modalidad_descuento_factura] || 'En Factura (Global)';
            var contTxt = parseInt(cp.precio_compra_contempla_descuento, 10) === 0 ? 'No' : 'Sí';
            var dtoProv = parseFloat(cp.proveedor_descuento_general) || 0;

            h += '<div class="card bg-light border-0 p-2 mb-2">'
               + '<div class="small fw-semibold text-secondary mb-1"><i class="fas fa-handshake me-1"></i>Condición Comercial Proveedor</div>'
               + '<div class="small d-flex justify-content-between text-muted">'
               + '<span>Modalidad: <strong>' + esc(modalTxt) + '</strong></span>'
               + '<span>Dto. acordado: <strong>' + (dtoProv > 0 ? esc(Fmt.numero(dtoProv, 2)) + '%' : '0%') + '</strong></span>'
               + '</div>'
               + '<div class="small text-muted mt-1">¿Contempla en costo?: <strong>' + esc(contTxt) + '</strong></div>'
               + '</div>';

            h += '<div class="form-check form-switch mb-2">'
               + '<input class="form-check-input" type="checkbox" role="switch" id="flLeerItems"' + (p.lectura_items === false ? '' : ' checked') + '>'
               + '<label class="form-check-label" for="flLeerItems">Cargar los ítems producto por producto</label> '
               + '<i class="fas fa-question-circle text-muted" title="Se guarda para este proveedor y vale para las próximas facturas (también en la carga de varios archivos). '
               + 'Apagado: una línea por alícuota con el producto de imputación del proveedor, para facturas de servicios como teléfono, gas o luz."></i>'
               + '</div>';
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
            var dto = p.descuento_general;   // descuento general aplicado a los ítems (precio de lista -> precio a importar)
            h += '<details class="mb-2"' + (dto ? ' open' : '') + '><summary class="fw-medium">Ítems del comprobante (' + p.items_referencia.length + ')'
               + (dto ? ' <span class="badge text-bg-warning fl-capa">desc. general ' + esc(Fmt.numero(dto.pct, 2)) + '%</span>' : '') + '</summary>'
               + '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Cód. proveedor</th><th>Nuestro código</th><th>Descripción</th><th class="text-end">Cant.</th>'
               + (dto ? '<th class="text-end">Precio lista</th><th class="text-end">Precio a importar</th>' : '<th class="text-end">Precio</th>')
               + '<th class="text-end">Subtotal</th></tr></thead><tbody>'
               + p.items_referencia.map(function (it) {
                   var nuestro = it.producto_id
                       ? '<span class="fw-medium">' + esc(it.producto_codigo || '') + '</span> <span class="badge text-bg-success fl-capa">cargado</span>'
                         + '<div class="small text-muted">' + esc(it.producto_nombre) + '</div>'
                       : '<span class="text-muted" title="El código del proveedor no está vinculado a ningún producto (Productos > Proveedores)">sin vincular</span>';
                   var precios = dto
                       ? '<td class="text-end text-muted"><s>' + importe(it.precio_lista) + '</s></td><td class="text-end fw-medium">' + importe(it.precio_importar) + '</td>'
                         + '<td class="text-end">' + importe(it.subtotal_importar) + '</td>'
                       : '<td class="text-end">' + importe(it.precio_lista !== undefined ? it.precio_lista : it.precio_unitario) + '</td><td class="text-end">' + importe(it.subtotal) + '</td>';
                   return '<tr><td>' + esc(it.codigo) + '</td><td>' + nuestro + '</td><td>' + esc(it.descripcion)
                        + '</td><td class="text-end">' + esc(Fmt.numero(it.cantidad)) + '</td>' + precios + '</tr>';
               }).join('') + '</tbody></table></div></details>';
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

    $('#flArchivo, #flCarpeta').on('change', function () {
        iniciarArchivos(this.files);
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
        if (!dt) return;
        // Con carpetas hay que recorrer las entradas; si no, alcanza con la lista de archivos
        var conCarpeta = dt.items && Array.prototype.some.call(dt.items, function (it) {
            var en = it.webkitGetAsEntry && it.webkitGetAsEntry();
            return en && en.isDirectory;
        });
        if (conCarpeta) leerEntradas(dt.items).then(iniciarArchivos);
        else iniciarArchivos(dt.files);
    });

    $('#btnFlNuevoLote').on('click', function () {
        if (estado.procesando) return;
        estado.lote = [];
        estado.loteIdx = null;
        reiniciarModalSubida();
    });

    // "Revisar": abre el formulario con la propuesta de esa fila; al cerrarlo se vuelve al lote
    $(document).on('click', '.fl-lote-revisar', function () {
        var i = parseInt($(this).data('idx'), 10);
        var f = estado.lote[i];
        if (!f || !f.propuesta) return;
        estado.archivo = f.archivo;
        estado.loteIdx = i;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalLecturaArchivo')).hide();
        aplicarPropuesta(f.propuesta);
    });

    $('#modalFacturaProveedor').on('hidden.bs.modal', function () {
        if (estado.desdeBandeja) {
            if (!estado.guardadaBandeja) {
                if (window.FP_EMBED && window.parent && window.parent !== window) {
                    window.parent.postMessage({ tipo: 'bdj_factura_cerrada' }, window.location.origin);
                } else {
                    window.location.href = 'bandeja_documentos.php';
                }
            }
            return;
        }
        if (estado.loteIdx === null || !estado.lote.length) return;
        estado.loteIdx = null;
        reiniciarModalSubida();
        renderLote();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalLecturaArchivo')).show();
    });

    $('#btnFlGuardarListos').on('click', function () {
        var filas = estado.lote.filter(function (f) { return f.estado === 'listo' && f.propuesta; });
        if (!filas.length) return;
        Swal.fire({
            icon: 'question', title: 'Guardar en Borrador',
            text: 'Se guardan ' + filas.length + ' factura(s) en Borrador con los datos leídos. Después se revisan y confirman como cualquier otra.',
            showCancelButton: true, confirmButtonText: 'Guardar', cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            var $b = $('#btnFlGuardarListos').prop('disabled', true);
            post({
                accion: 'lectura_guardar_lote', sucursal_id: $('#flLoteSucursal').val() || 0,
                adjunto_ids: filas.map(function (f) { return f.propuesta.adjunto_id; })
            }).then(function (res) {
                if (!res || !res.success) throw { message: (res && res.message) || 'No se pudo guardar.' };
                (res.data || []).forEach(function (it) {
                    var f = filas.find(function (x) { return String(x.propuesta.adjunto_id) === String(it.adjunto_id); });
                    if (!f) return;
                    if (it.success) {
                        f.estado = 'guardada';
                        f.facturaId = it.factura_proveedor_id;
                    } else {
                        f.estado = 'revisar';
                        f.mensaje = [it.message].concat(it.motivos || []).join(' · ');
                    }
                });
                renderLote();
                $('#btnRecargar').trigger('click');   // tabla de facturas
                if (window.UiSimple && window.UiSimple.ok) window.UiSimple.ok(res.message);
                else Swal.fire({ icon: 'success', title: 'Listo', text: res.message });
            }).catch(function (e) {
                renderLote();
                Swal.fire({ icon: 'error', title: 'No se pudo completar', text: textoError(e, 'No se pudo guardar.') });
            });
        });
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

    $(document).on('change', '#flLeerItems', function () {
        var p = estado.propuesta, $sw = $(this), leer = $sw.is(':checked');
        if (!p) return;
        Swal.fire({
            icon: 'question', title: leer ? 'Cargar producto por producto' : 'Una línea por alícuota',
            text: (leer ? 'Este proveedor va a cargar los ítems del comprobante producto por producto.'
                        : 'Este proveedor va a cargar una línea por alícuota, sin los ítems (servicios como teléfono, gas o luz).')
                + ' Se guarda para las próximas facturas y el formulario se vuelve a cargar con lo leído (se pierden los cambios hechos a mano).',
            showCancelButton: true, confirmButtonText: 'Aplicar', cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (!r.isConfirmed) { $sw.prop('checked', !leer); return; }
            $sw.prop('disabled', true);
            post({ accion: 'lectura_items_proveedor', adjunto_id: p.adjunto_id, leer_items: leer ? 1 : 0 }).then(function (res) {
                if (!res || !res.success) throw { message: (res && res.message) || 'No se pudo guardar.' };
                aplicarPropuesta(res);
            }).catch(function (e) {
                $sw.prop('checked', !leer).prop('disabled', false);
                Swal.fire({ icon: 'error', title: 'No se pudo completar', text: e.responseJSON ? mensajeError(e, 'No se pudo guardar.') : (e.message || 'No se pudo guardar.') });
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

    // "Revisar" desde la Bandeja de Documentos (?lectura_adjunto=N): abre el formulario con la última lectura de ese
    // archivo, como la fila "Revisar" de un lote. Sin el botón "Cargar desde archivo" (sin permiso de Agregar) no hace nada.
    (function () {
        var m = /[?&]lectura_adjunto=(\d+)/.exec(window.location.search);
        if (!m || !$('#btnCargarArchivo').length) return;
        estado.desdeBandeja = true;
        estado.guardadaBandeja = false;
        if (window.history && window.history.replaceState) {   // que recargar la página no lo vuelva a abrir
            window.history.replaceState(null, '', window.location.pathname + window.location.search.replace(/([?&])lectura_adjunto=\d+&?/, '$1').replace(/[?&]$/, ''));
        }
        post({ accion: 'lectura_propuesta', adjunto_id: m[1] }).then(function (res) {
            if (!res || !res.success) throw { message: (res && res.message) || 'No se pudo abrir el documento.' };
            estado.archivo = null;
            estado.loteIdx = null;
            aplicarPropuesta(res);
        }).catch(function (e) {
            Swal.fire({ icon: 'error', title: 'No se pudo completar', text: e.responseJSON ? mensajeError(e, 'No se pudo abrir el documento.') : (e.message || 'No se pudo abrir el documento.') }).then(function () {
                if (window.FP_EMBED && window.parent && window.parent !== window) {
                    window.parent.postMessage({ tipo: 'bdj_factura_cerrada' }, window.location.origin);
                } else {
                    window.location.href = 'bandeja_documentos.php';
                }
            });
        });
    })();

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
