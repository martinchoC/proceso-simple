/**
 * Mejoras de interfaz. Todo es progresivo: si el JS no carga, los formularios
 * siguen funcionando con POST tradicional y la validación real está en el
 * servidor. No hay JS inline: la CSP del proyecto lo bloquea.
 */
(function () {
    'use strict';

    var meta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = meta ? meta.getAttribute('content') : '';

    /* ── Notificaciones Toast (SweetAlert2) ────────────────────────────────── */

    var Toast = null;

    function obtenerToast() {
        if (Toast) return Toast;
        if (typeof Swal !== 'undefined') {
            Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2800,
                timerProgressBar: true,
                didOpen: function (toast) {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
            return Toast;
        }
        return null;
    }

    function mostrarAviso(texto, tipo) {
        if (typeof tipo === 'boolean') {
            tipo = tipo ? 'error' : 'success';
        }
        if (!tipo) tipo = 'info';

        var toastInst = obtenerToast();
        if (toastInst) {
            toastInst.fire({
                icon: tipo,
                title: texto
            });
            return;
        }

        // Fallback flotante no invasivo (no altera el DOM del template del catálogo)
        var previo = document.querySelector('[data-aviso-flotante]');
        if (previo) previo.remove();

        var aviso = document.createElement('div');
        aviso.setAttribute('data-aviso-flotante', '');
        aviso.style.position = 'fixed';
        aviso.style.top = '1.25rem';
        aviso.style.right = '1.25rem';
        aviso.style.zIndex = '99999';
        aviso.style.padding = '0.75rem 1.25rem';
        aviso.style.borderRadius = '8px';
        aviso.style.boxShadow = '0 8px 24px rgba(0,0,0,0.18)';
        aviso.style.color = '#fff';
        aviso.style.background = tipo === 'error' ? '#ef4444' : (tipo === 'warning' ? '#f59e0b' : '#10b981');
        aviso.style.fontWeight = '500';
        aviso.style.fontSize = '0.9rem';
        aviso.textContent = texto;
        document.body.appendChild(aviso);
        setTimeout(function () { aviso.remove(); }, 3000);
    }

    /* ── Contador del carrito en cabecera ─────────────────────────────────── */

    function actualizarContador(lineas) {
        var enlaces = document.querySelectorAll('header a[href$="/carrito"]');
        if (!enlaces.length) return;

        enlaces.forEach(function (enlace) {
            var badge = enlace.querySelector('[data-carrito-contador]');
            if (!badge) {
                if (lineas <= 0) return;
                badge = document.createElement('span');
                badge.className = 'badge-carrito';
                badge.setAttribute('data-carrito-contador', '');
                enlace.appendChild(badge);
            }
            if (lineas <= 0) { badge.remove(); return; }
            badge.textContent = String(lineas);
        });
    }

    /* ── Sincronizar cantidades y botones en el catálogo ──────────────────── */

    var ultimoEstadoCarrito = null;

    function sincronizarCantidadesCatalogo(carrito) {
        if (carrito) {
            ultimoEstadoCarrito = carrito;
        } else {
            carrito = ultimoEstadoCarrito;
        }
        var itemsMap = {};
        if (carrito && carrito.items) {
            carrito.items.forEach(function (item) {
                itemsMap[String(item.producto_id)] = item.cantidad;
            });
        }

        // Catálogo dinámico interactivo (+ y -)
        var inputs = document.querySelectorAll('input[data-prod-id]');
        inputs.forEach(function (input) {
            var pid = input.getAttribute('data-prod-id');
            var cantEnCarrito = itemsMap[pid] !== undefined ? itemsMap[pid] : 0;
            input.setAttribute('data-cant-confirmada', String(cantEnCarrito));
            if (document.activeElement !== input) {
                input.value = String(cantEnCarrito);
            }
            var contenedor = input.closest('.cantidad');
            if (contenedor) {
                contenedor.classList.toggle('con-unidades', cantEnCarrito > 0);
                var btnMenos = contenedor.querySelector('button[data-paso="-1"]');
                if (btnMenos) {
                    btnMenos.disabled = (cantEnCarrito <= 0);
                }
            }
        });

        // Formularios tradicionales (si existen en detalle u otras vistas)
        var forms = document.querySelectorAll('[data-form-producto]');
        forms.forEach(function (form) {
            var pid = form.getAttribute('data-form-producto');
            var input = form.querySelector('input[name="cantidad"]');
            var btn = form.querySelector('[data-btn-accion]');
            var cantEnCarrito = itemsMap[pid] !== undefined ? itemsMap[pid] : 0;

            if (input && document.activeElement !== input) {
                input.value = String(cantEnCarrito);
            }
            if (btn) {
                if (cantEnCarrito > 0) {
                    btn.textContent = form.classList.contains('form-agregar') && form.querySelector('.btn-bloque') ? 'Actualizar en el carrito' : 'Actualizar';
                    btn.classList.remove('btn-primario');
                    btn.classList.add('btn-secundario');
                } else {
                    btn.textContent = form.classList.contains('form-agregar') && form.querySelector('.btn-bloque') ? 'Agregar al carrito' : 'Agregar';
                    btn.classList.remove('btn-secundario');
                    btn.classList.add('btn-primario');
                }
            }
        });
    }

    /* ── Selector de cantidad (+ / −) y ejecución en tiempo real ─────────── */

    var timersCambioCantidad = {};

    function programarFijarCantidad(prodId, valor, campo, urlFijar) {
        if (timersCambioCantidad[prodId]) {
            clearTimeout(timersCambioCantidad[prodId]);
        }
        var cantContenedor = campo ? campo.closest('.cantidad') : null;
        if (cantContenedor) {
            cantContenedor.classList.add('cargando-cantidad');
        }
        timersCambioCantidad[prodId] = setTimeout(function () {
            delete timersCambioCantidad[prodId];
            fijarCantidadProducto(prodId, valor, campo, urlFijar);
        }, 280);
    }

    function fijarCantidadProducto(prodId, nuevaCantidad, campo, urlFijar) {
        var cantConfirmada = campo ? parseInt(campo.getAttribute('data-cant-confirmada') || '0', 10) : 0;
        var cantContenedor = campo ? campo.closest('.cantidad') : null;

        if (campo && nuevaCantidad === cantConfirmada && (!cantContenedor || !cantContenedor.classList.contains('cargando-cantidad'))) {
            return;
        }

        var formData = new FormData();
        formData.append('producto_id', String(prodId));
        formData.append('cantidad', String(nuevaCantidad));

        fetch(urlFijar, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken
            }
        })
            .then(function (respuesta) { return respuesta.json(); })
            .then(function (datos) {
                if (cantContenedor) cantContenedor.classList.remove('cargando-cantidad');

                if (datos && datos.ok) {
                    if (campo) {
                        campo.setAttribute('data-cant-confirmada', String(nuevaCantidad));
                        campo.value = String(nuevaCantidad);
                        if (cantContenedor) {
                            cantContenedor.classList.toggle('con-unidades', nuevaCantidad > 0);
                            var btnMenos = cantContenedor.querySelector('button[data-paso="-1"]');
                            if (btnMenos) {
                                var attrMin = campo.getAttribute('min');
                                var minVal = attrMin !== null ? parseInt(attrMin, 10) : 0;
                                if (isNaN(minVal)) minVal = 0;
                                btnMenos.disabled = (nuevaCantidad <= minVal);
                            }
                        }
                    }

                    // Notificaciones discretas con SweetAlert2
                    if (cantConfirmada === 0 && nuevaCantidad > 0) {
                        mostrarAviso('Producto agregado al carrito', 'success');
                    } else if (cantConfirmada > 0 && nuevaCantidad === 0) {
                        mostrarAviso('Producto quitado del carrito', 'info');
                    }

                    if (datos.carrito) {
                        actualizarContador(datos.carrito.lineas);
                        actualizarCarritoLateral(datos.carrito);
                        sincronizarCantidadesCatalogo(datos.carrito);
                        actualizarPaginaCarrito(datos.carrito);
                    }
                } else {
                    if (campo) {
                        campo.value = String(cantConfirmada);
                        if (cantContenedor) {
                            cantContenedor.classList.toggle('con-unidades', cantConfirmada > 0);
                            var btnM = cantContenedor.querySelector('button[data-paso="-1"]');
                            if (btnM) {
                                var attrMinM = campo.getAttribute('min');
                                var minValM = attrMinM !== null ? parseInt(attrMinM, 10) : 0;
                                if (isNaN(minValM)) minValM = 0;
                                btnM.disabled = (cantConfirmada <= minValM);
                            }
                        }
                    }
                    mostrarAviso((datos && datos.error) || 'No se pudo actualizar la cantidad.', 'error');
                }
            })
            .catch(function () {
                if (cantContenedor) cantContenedor.classList.remove('cargando-cantidad');
                if (campo) {
                    campo.value = String(cantConfirmada);
                    if (cantContenedor) {
                        cantContenedor.classList.toggle('con-unidades', cantConfirmada > 0);
                        var btnM = cantContenedor.querySelector('button[data-paso="-1"]');
                        if (btnM) {
                            var attrMinC = campo.getAttribute('min');
                            var minValC = attrMinC !== null ? parseInt(attrMinC, 10) : 0;
                            if (isNaN(minValC)) minValC = 0;
                            btnM.disabled = (cantConfirmada <= minValC);
                        }
                    }
                }
                mostrarAviso('Error de conexión al actualizar el carrito.', 'error');
            });
    }

    // Evento click en botones + y -
    document.addEventListener('click', function (evento) {
        var boton = evento.target.closest('[data-cantidad] button[data-paso]');
        if (!boton) return;

        var contenedorCantidad = boton.closest('[data-cantidad]');
        var campo = contenedorCantidad ? contenedorCantidad.querySelector('input[type="number"]') : null;
        if (!campo) return;

        var paso = parseInt(boton.getAttribute('data-paso'), 10) || 1;
        var attrMin = campo.getAttribute('min');
        var minimo = attrMin !== null ? parseInt(attrMin, 10) : 0;
        if (isNaN(minimo)) minimo = 0;
        var maximo = parseInt(campo.getAttribute('max'), 10) || 99999;
        var actual = parseInt(campo.value, 10);
        if (isNaN(actual)) actual = minimo;
        var valor = Math.min(Math.max(actual + paso, minimo), maximo);

        campo.value = String(valor);

        // Actualizar aspecto visual inmediato
        contenedorCantidad.classList.toggle('con-unidades', valor > 0);
        var btnMenos = contenedorCantidad.querySelector('button[data-paso="-1"]');
        if (btnMenos) {
            btnMenos.disabled = (valor <= 0);
        }

        // Si es el catálogo interactivo en tiempo real
        var prodId = campo.getAttribute('data-prod-id') || (campo.closest('[data-producto-id]') ? campo.closest('[data-producto-id]').getAttribute('data-producto-id') : null);
        var contenedorAcciones = campo.closest('[data-url-fijar]');
        var urlFijar = contenedorAcciones ? contenedorAcciones.getAttribute('data-url-fijar') : null;

        if (prodId && urlFijar) {
            programarFijarCantidad(prodId, valor, campo, urlFijar);
        }
    });

    // Evento input y change al teclear la cantidad manualmente
    document.addEventListener('input', function (evento) {
        var campo = evento.target;
        if (!campo.matches || !campo.matches('input[data-prod-id]')) return;

        var contenedorCantidad = campo.closest('[data-cantidad]');
        var prodId = campo.getAttribute('data-prod-id');
        var contenedorAcciones = campo.closest('[data-url-fijar]');
        var urlFijar = contenedorAcciones ? contenedorAcciones.getAttribute('data-url-fijar') : null;

        var valor = parseInt(campo.value, 10);
        if (isNaN(valor) || valor < 0) {
            valor = 0;
        }
        if (valor > 99999) {
            valor = 99999;
            campo.value = '99999';
        }

        var attrMin = campo.getAttribute('min');
        var minVal = attrMin !== null ? parseInt(attrMin, 10) : 0;
        if (isNaN(minVal)) minVal = 0;

        if (contenedorCantidad) {
            contenedorCantidad.classList.toggle('con-unidades', valor > 0);
            var btnMenos = contenedorCantidad.querySelector('button[data-paso="-1"]');
            if (btnMenos) {
                btnMenos.disabled = (valor <= minVal);
            }
        }

        if (prodId && urlFijar) {
            programarFijarCantidad(prodId, valor, campo, urlFijar);
        }
    });

    document.addEventListener('change', function (evento) {
        var campo = evento.target;
        if (!campo.matches || !campo.matches('input[data-prod-id]')) return;
        var attrMin = campo.getAttribute('min');
        var minVal = attrMin !== null ? parseInt(attrMin, 10) : 0;
        if (isNaN(minVal)) minVal = 0;

        var valor = parseInt(campo.value, 10);
        if (isNaN(valor) || valor < minVal) {
            campo.value = String(minVal);
            var prodId = campo.getAttribute('data-prod-id');
            var contenedorAcciones = campo.closest('[data-url-fijar]');
            var urlFijar = contenedorAcciones ? contenedorAcciones.getAttribute('data-url-fijar') : null;
            if (prodId && urlFijar) {
                programarFijarCantidad(prodId, minVal, campo, urlFijar);
            }
        }
    });

    // Quitar producto desde el carrito lateral (botón ×)
    document.addEventListener('click', function (evento) {
        var boton = evento.target.closest('[data-quitar-item]');
        if (!boton) return;

        var prodId = boton.getAttribute('data-quitar-item');
        if (!prodId) return;

        var campo = document.querySelector('input[data-prod-id="' + prodId + '"]');
        var contenedorAcciones = document.querySelector('[data-url-fijar]');
        var urlFijar = contenedorAcciones ? contenedorAcciones.getAttribute('data-url-fijar') : null;

        if (campo) {
            campo.value = '0';
            var contenedorCantidad = campo.closest('[data-cantidad]');
            if (contenedorCantidad) {
                contenedorCantidad.classList.remove('con-unidades');
                var btnMenos = contenedorCantidad.querySelector('button[data-paso="-1"]');
                if (btnMenos) btnMenos.disabled = true;
            }
        }

        if (!urlFijar) {
            var metaUrl = document.querySelector('meta[name="url-fijar-carrito"]');
            urlFijar = metaUrl ? metaUrl.getAttribute('content') : '/carrito/items/fijar';
        }

        if (timersCambioCantidad[prodId]) {
            clearTimeout(timersCambioCantidad[prodId]);
            delete timersCambioCantidad[prodId];
        }

        fijarCantidadProducto(prodId, 0, campo, urlFijar);
    });

    /* ── Agregar o actualizar en el carrito tradicional (formulario de detalle) ─── */

    document.addEventListener('submit', function (evento) {
        var form = evento.target;
        if (!(form instanceof HTMLFormElement) || !form.classList.contains('form-agregar')) return;

        evento.preventDefault();

        var campoCant = form.querySelector('input[name="cantidad"]');
        var cantidad = campoCant ? parseFloat(campoCant.value) : 1;
        if (isNaN(cantidad)) cantidad = 0;

        var btn = form.querySelector('button[type="submit"]');
        var textoBtn = btn ? btn.textContent.trim().toLowerCase() : '';
        var estabaEnCarrito = textoBtn.indexOf('actualizar') !== -1 || textoBtn.indexOf('modificar') !== -1;

        if (cantidad <= 0 && !estabaEnCarrito) {
            mostrarAviso('Elegí una cantidad mayor a 0 para agregar al carrito.', 'warning');
            if (campoCant) campoCant.focus();
            return;
        }

        var textoOriginal = btn ? btn.textContent : '';
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Guardando...';
        }

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken
            }
        })
            .then(function (respuesta) { return respuesta.json(); })
            .then(function (datos) {
                if (datos && datos.ok) {
                    mostrarAviso(datos.mensaje || 'Carrito actualizado.', 'success');
                    if (datos.carrito) {
                        actualizarContador(datos.carrito.lineas);
                        actualizarCarritoLateral(datos.carrito);
                        sincronizarCantidadesCatalogo(datos.carrito);
                    }
                } else {
                    mostrarAviso((datos && datos.error) || 'No se pudo completar la acción.', 'error');
                }
            })
            .catch(function () {
                mostrarAviso('Error de conexión. Reintentá.', 'error');
            })
            .finally(function () {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = textoOriginal;
                }
            });
    });

    /* ── Carrito lateral dinámico en catálogo ─────────────────────────────── */

    function actualizarCarritoLateral(carrito) {
        var panel = document.querySelector('[data-carrito-lateral]');
        if (!panel || !carrito) return;

        var conteo = panel.querySelector('[data-carrito-lateral-conteo]');
        if (conteo) conteo.textContent = String(carrito.lineas || 0);

        var vacio = panel.querySelector('[data-carrito-lateral-vacio]');
        var contenido = panel.querySelector('[data-carrito-lateral-contenido]');
        var lista = panel.querySelector('[data-carrito-lateral-items]');

        if (!carrito.items || carrito.items.length === 0) {
            if (vacio) vacio.classList.remove('oculto');
            if (contenido) contenido.classList.add('oculto');
            return;
        }

        if (vacio) vacio.classList.add('oculto');
        if (contenido) contenido.classList.remove('oculto');

        if (lista) {
            lista.innerHTML = '';
            carrito.items.forEach(function (item) {
                var li = document.createElement('li');
                li.className = 'carrito-item-mini nuevo';
                li.setAttribute('data-item-id', String(item.producto_id));

                var info = document.createElement('div');
                info.className = 'carrito-item-mini-info';

                var top = document.createElement('div');
                top.className = 'carrito-item-mini-top';

                var cod = document.createElement('span');
                cod.className = 'carrito-item-mini-codigo';
                cod.textContent = item.codigo;

                var btnQuitar = document.createElement('button');
                btnQuitar.type = 'button';
                btnQuitar.className = 'carrito-item-mini-quitar';
                btnQuitar.setAttribute('data-quitar-item', String(item.producto_id));
                btnQuitar.title = 'Quitar del carrito';
                btnQuitar.innerHTML = '&times;';

                top.appendChild(cod);
                top.appendChild(btnQuitar);

                var nom = document.createElement('strong');
                nom.className = 'carrito-item-mini-nombre';
                nom.textContent = item.nombre;

                var cant = document.createElement('span');
                cant.className = 'carrito-item-mini-cant';
                var cantNum = Number(item.cantidad);
                cant.textContent = cantNum + (cantNum === 1 ? ' unidad' : ' unidades');

                var precios = document.createElement('div');
                precios.className = 'carrito-item-mini-precios';

                // 1. Precio de Lista (sin IVA)
                var fLista = document.createElement('div');
                fLista.className = 'mini-precio-fila mini-precio-lista';
                var listaTotal = (cantNum > 1 && item.precio_lista_total_fmt) ? item.precio_lista_total_fmt : (item.precio_lista_unit_fmt || item.precio_lista_total_fmt || '');
                var extraUnit = (cantNum > 1 && item.precio_lista_unit_fmt) ? ' <small class="mini-unitario">(' + item.precio_lista_unit_fmt + ' c/u)</small>' : '';
                fLista.innerHTML = '<span class="mini-label">Precio de Lista:</span> <span class="mini-valor">' + listaTotal + extraUnit + '</span>';
                precios.appendChild(fLista);

                // 2. Descuento (%)
                var descPct = Number(item.descuento_pct || 0);
                if (descPct > 0) {
                    var fDesc = document.createElement('div');
                    fDesc.className = 'mini-precio-fila mini-precio-descuento';
                    var descTotal = item.descuento_total_fmt ? ('&minus; ' + item.descuento_total_fmt) : '$ 0,00';
                    fDesc.innerHTML = '<span class="mini-label">Descuento (' + Math.round(descPct) + '%):</span> <span class="mini-valor texto-descuento">' + descTotal + '</span>';
                    precios.appendChild(fDesc);
                }

                // 3. Neto
                var fNeto = document.createElement('div');
                fNeto.className = 'mini-precio-fila mini-precio-neto';
                var netoVal = item.neto_fmt || item.subtotal_neto_desc_fmt || item.precio_neto_desc_fmt || '';
                fNeto.innerHTML = '<span class="mini-label">Neto:</span> <strong class="mini-valor">' + netoVal + '</strong>';
                precios.appendChild(fNeto);

                info.appendChild(top);
                info.appendChild(nom);
                info.appendChild(cant);
                info.appendChild(precios);

                li.appendChild(info);
                lista.appendChild(li);
            });
        }

        var subtotalBrutoEl = panel.querySelector('[data-carrito-lateral-subtotal-bruto]');
        if (subtotalBrutoEl && carrito.subtotal_bruto_fmt) {
            subtotalBrutoEl.textContent = carrito.subtotal_bruto_fmt;
        }

        var descFila = panel.querySelector('[data-carrito-lateral-descuento-fila]');
        var descMonto = panel.querySelector('[data-carrito-lateral-descuento]');
        var descEtiqueta = panel.querySelector('[data-carrito-lateral-descuento-etiqueta]');
        var descPct = carrito.descuento_pct || 0;

        var subDescEl = panel.querySelector('[data-carrito-lateral-descuento-subtitulo]');
        var subDescPctEl = panel.querySelector('[data-carrito-lateral-descuento-subtitulo-pct]');
        if (subDescEl) {
            if (descPct > 0) {
                subDescEl.classList.remove('oculto');
                if (subDescPctEl) {
                    subDescPctEl.textContent = Math.round(descPct) + '%';
                }
            } else {
                subDescEl.classList.add('oculto');
            }
        }

        if (descFila) {
            if (descPct > 0) {
                descFila.classList.remove('oculto');
                if (descEtiqueta) descEtiqueta.textContent = 'Descuento (' + Math.round(descPct) + '%)';
                if (descMonto) {
                    descMonto.textContent = (carrito.descuento_fmt && carrito.descuento_importe > 0)
                        ? ('\u2212 ' + carrito.descuento_fmt)
                        : (Math.round(descPct) + '%');
                }
            } else {
                descFila.classList.add('oculto');
            }
        }

        var subtotalNetoEl = panel.querySelector('[data-carrito-lateral-subtotal-neto]') || panel.querySelector('[data-carrito-lateral-subtotal]');
        if (subtotalNetoEl && (carrito.subtotal_neto_fmt || carrito.subtotal_fmt)) {
            subtotalNetoEl.textContent = carrito.subtotal_neto_fmt || carrito.subtotal_fmt;
        }

        var ivaEtiqueta = panel.querySelector('[data-carrito-lateral-iva-etiqueta]');
        if (ivaEtiqueta && carrito.iva_pct) {
            ivaEtiqueta.textContent = 'IVA (' + Math.round(carrito.iva_pct) + '%)';
        }

        var ivaEl = panel.querySelector('[data-carrito-lateral-iva]');
        if (ivaEl && carrito.iva_fmt) ivaEl.textContent = carrito.iva_fmt;

        var totalEl = panel.querySelector('[data-carrito-lateral-total]');
        if (totalEl && carrito.total_fmt) totalEl.textContent = carrito.total_fmt;
    }

    /* ── Vista principal del carrito (/carrito) dinámico ──────────────────── */

    function actualizarPaginaCarrito(carrito) {
        var contenedorCarrito = document.querySelector('.carrito');
        if (!contenedorCarrito || !carrito) return;

        // 1. Si el carrito quedó sin líneas, recargar para mostrar pantalla de carrito vacío
        if (!carrito.lineas || carrito.lineas <= 0) {
            window.location.reload();
            return;
        }

        // 2. Título de cantidad de productos
        var elTitulo = document.querySelector('[data-carrito-titulo]');
        if (elTitulo) {
            elTitulo.textContent = carrito.lineas + ' producto(s)';
        }

        // 3. Mapear items por producto_id
        var itemsMap = {};
        if (Array.isArray(carrito.items)) {
            carrito.items.forEach(function (it) {
                itemsMap[String(it.producto_id)] = it;
            });
        }

        // 4. Actualizar filas de productos
        var lineasDOM = document.querySelectorAll('.linea[data-linea-id]');
        lineasDOM.forEach(function (fila) {
            var prodId = fila.getAttribute('data-linea-id');
            var item = itemsMap[prodId];

            if (!item) {
                fila.remove();
                return;
            }

            // Actualizar input y estado del botón restar
            var campo = fila.querySelector('input[data-prod-id]');
            if (campo) {
                campo.value = String(item.cantidad);
                campo.setAttribute('data-cant-confirmada', String(item.cantidad));
                var btnMenos = fila.querySelector('button[data-paso="-1"]');
                if (btnMenos) {
                    var attrMin = campo.getAttribute('min');
                    var minVal = attrMin !== null ? parseInt(attrMin, 10) : 1;
                    if (isNaN(minVal)) minVal = 1;
                    btnMenos.disabled = (item.cantidad <= minVal);
                }
            }

            // Precio de lista
            var elPrecioLista = fila.querySelector('[data-precio-lista]');
            if (elPrecioLista) {
                if (parseFloat(item.cantidad) > 1) {
                    elPrecioLista.innerHTML = item.precio_lista_total_fmt + ' <small class="mini-unitario">(' + item.precio_lista_unit_fmt + ' c/u)</small>';
                } else {
                    elPrecioLista.textContent = item.precio_lista_unit_fmt;
                }
            }

            // Descuento
            var filaDesc = fila.querySelector('[data-fila-descuento]');
            if (filaDesc) {
                var descPct = parseFloat(item.descuento_pct) || 0;
                filaDesc.classList.toggle('oculto', descPct <= 0);
                var elDescPct = fila.querySelector('[data-desc-pct]');
                if (elDescPct) {
                    elDescPct.textContent = String(Math.round(descPct));
                }
                var elPrecioDesc = fila.querySelector('[data-precio-descuento]');
                if (elPrecioDesc) {
                    elPrecioDesc.innerHTML = '&minus; ' + item.descuento_total_fmt;
                }
            }

            // Neto
            var elNeto = fila.querySelector('[data-precio-neto]');
            if (elNeto) {
                elNeto.textContent = item.neto_fmt;
            }
        });

        // 5. Actualizar panel lateral de resumen
        var subBrutoEl = document.querySelector('[data-resumen-subtotal-bruto]');
        if (subBrutoEl && carrito.subtotal_bruto_fmt) {
            subBrutoEl.textContent = carrito.subtotal_bruto_fmt;
        }

        var filaDescResumen = document.querySelector('[data-resumen-fila-descuento]');
        if (filaDescResumen) {
            var descImporte = parseFloat(carrito.descuento_importe) || 0;
            var descPctResumen = parseFloat(carrito.descuento_pct) || 0;
            filaDescResumen.classList.toggle('oculto', descImporte <= 0 && descPctResumen <= 0);

            var descPctEl = document.querySelector('[data-resumen-descuento-pct]');
            if (descPctEl) {
                descPctEl.textContent = String(Math.round(descPctResumen));
            }

            var descImpEl = document.querySelector('[data-resumen-descuento-importe]');
            if (descImpEl) {
                if (descImporte > 0) {
                    descImpEl.innerHTML = '&minus; ' + carrito.descuento_fmt;
                } else {
                    descImpEl.textContent = Math.round(descPctResumen) + '%';
                }
            }
        }

        var subNetoEl = document.querySelector('[data-resumen-subtotal-neto]');
        if (subNetoEl && carrito.subtotal_neto_fmt) {
            subNetoEl.textContent = carrito.subtotal_neto_fmt;
        }

        var ivaPctEl = document.querySelector('[data-resumen-iva-pct]');
        if (ivaPctEl && carrito.iva_pct) {
            ivaPctEl.textContent = String(Math.round(carrito.iva_pct));
        }

        var ivaEl = document.querySelector('[data-resumen-iva]');
        if (ivaEl && carrito.iva_fmt) {
            ivaEl.textContent = carrito.iva_fmt;
        }

        var totalEl = document.querySelector('[data-resumen-total]');
        if (totalEl && carrito.total_fmt) {
            totalEl.textContent = carrito.total_fmt;
        }
    }

    // Quitar producto desde la vista principal de carrito sin recarga de página
    document.addEventListener('submit', function (evento) {
        var form = evento.target.closest('form[data-form-quitar]');
        if (!form) return;

        evento.preventDefault();
        var formData = new FormData(form);
        var url = form.getAttribute('action');

        var botonQuitar = form.querySelector('button[type="submit"]');
        if (botonQuitar) {
            botonQuitar.disabled = true;
            botonQuitar.textContent = '...';
        }

        fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken
            }
        })
            .then(function (res) { return res.json(); })
            .then(function (datos) {
                if (datos && datos.ok && datos.carrito) {
                    mostrarAviso('Producto quitado del carrito', 'info');
                    actualizarContador(datos.carrito.lineas);
                    actualizarCarritoLateral(datos.carrito);
                    sincronizarCantidadesCatalogo(datos.carrito);
                    actualizarPaginaCarrito(datos.carrito);
                } else {
                    form.submit();
                }
            })
            .catch(function () {
                form.submit();
            });
    });

    /* ── Filtros plegables en mobile ──────────────────────────────────────── */

    document.addEventListener('click', function (evento) {
        var boton = evento.target.closest('[data-filtros-toggle]');
        if (!boton) return;

        var cuerpo = document.querySelector('[data-filtros-cuerpo]');
        if (cuerpo) cuerpo.classList.toggle('abierto');
    });

    /* ── Alternar vista de productos (Tarjetas / Lista formato tabla) ─────── */

    function obtenerModoVistaGuardado() {
        var vista = 'lista';
        try {
            vista = localStorage.getItem('ecom_vista_catalogo') || 'lista';
        } catch (e) { }
        return vista;
    }

    function aplicarModoVista(vista) {
        if (!vista) {
            vista = obtenerModoVistaGuardado();
        }
        var grilla = document.querySelector('[data-grilla-productos]');
        var botones = document.querySelectorAll('[data-vista]');
        var tablaCabecera = document.querySelector('[data-tabla-cabecera]');

        if (grilla) {
            if (vista === 'lista') {
                grilla.classList.add('vista-lista');
                if (tablaCabecera) tablaCabecera.classList.remove('oculto');
            } else {
                grilla.classList.remove('vista-lista');
                if (tablaCabecera) tablaCabecera.classList.add('oculto');
            }
        }
        if (botones && botones.length) {
            botones.forEach(function (btn) {
                var coincide = btn.getAttribute('data-vista') === vista;
                btn.classList.toggle('activo', coincide);
                btn.setAttribute('aria-pressed', String(coincide));
            });
        }
        try {
            localStorage.setItem('ecom_vista_catalogo', vista);
        } catch (e) { }
    }

    function inicializarModoVista() {
        aplicarModoVista();

        document.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-vista]');
            if (!boton) return;
            var vista = boton.getAttribute('data-vista');
            if (vista) aplicarModoVista(vista);
        });
    }

    function inicializarFiltrosVehiculo() {
        var selectMarca = document.querySelector('[data-select-marca]');
        var selectModelo = document.querySelector('[data-select-modelo]');
        var selectSubmodelo = document.querySelector('[data-select-submodelo]');
        if (!selectMarca || !selectModelo || !selectSubmodelo) return;

        var urlModelos = selectMarca.getAttribute('data-url-modelos') || '/catalogo/modelos';
        var urlSubmodelos = selectModelo.getAttribute('data-url-submodelos') || '/catalogo/submodelos';

        function limpiarSelect(select, placeholder) {
            select.innerHTML = '';
            var opt = document.createElement('option');
            opt.value = '0';
            opt.textContent = placeholder;
            select.appendChild(opt);
            select.value = '0';
        }

        selectMarca.addEventListener('change', function () {
            var marcaId = parseInt(selectMarca.value, 10) || 0;
            if (marcaId <= 0) {
                limpiarSelect(selectModelo, 'Seleccione una marca');
                selectModelo.disabled = true;
                limpiarSelect(selectSubmodelo, 'Seleccione un modelo');
                selectSubmodelo.disabled = true;
                return;
            }

            limpiarSelect(selectModelo, 'Cargando modelos...');
            selectModelo.disabled = true;
            limpiarSelect(selectSubmodelo, 'Seleccione un modelo');
            selectSubmodelo.disabled = true;

            fetch(urlModelos + '?marca_id=' + marcaId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    limpiarSelect(selectModelo, 'Todos los modelos');
                    var modelos = (data && data.modelos) ? data.modelos : [];
                    if (modelos.length > 0) {
                        modelos.forEach(function (m) {
                            var opt = document.createElement('option');
                            opt.value = String(m.modelo_id);
                            opt.textContent = m.modelo_nombre;
                            selectModelo.appendChild(opt);
                        });
                        selectModelo.disabled = false;
                    } else {
                        limpiarSelect(selectModelo, 'Sin modelos disponibles');
                        selectModelo.disabled = true;
                    }
                })
                .catch(function () {
                    limpiarSelect(selectModelo, 'Todos los modelos');
                    selectModelo.disabled = false;
                });
        });

        selectModelo.addEventListener('change', function () {
            var modeloId = parseInt(selectModelo.value, 10) || 0;
            if (modeloId <= 0) {
                limpiarSelect(selectSubmodelo, 'Seleccione un modelo');
                selectSubmodelo.disabled = true;
                return;
            }

            limpiarSelect(selectSubmodelo, 'Cargando submodelos...');
            selectSubmodelo.disabled = true;

            fetch(urlSubmodelos + '?modelo_id=' + modeloId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    limpiarSelect(selectSubmodelo, 'Todos los submodelos');
                    var submodelos = (data && data.submodelos) ? data.submodelos : [];
                    if (submodelos.length > 0) {
                        submodelos.forEach(function (sm) {
                            var opt = document.createElement('option');
                            opt.value = String(sm.submodelo_id);
                            opt.textContent = sm.submodelo_nombre;
                            selectSubmodelo.appendChild(opt);
                        });
                        selectSubmodelo.disabled = false;
                    } else {
                        limpiarSelect(selectSubmodelo, 'Todos los submodelos');
                        selectSubmodelo.disabled = false;
                    }
                })
                .catch(function () {
                    limpiarSelect(selectSubmodelo, 'Todos los submodelos');
                    selectSubmodelo.disabled = false;
                });
        });
    }

    /* ── Buscador dinámico por palabras (Pills) y filtrado AJAX ─────────── */

    function inicializarBuscadorDinamico() {
        var form = document.querySelector('[data-buscador-form]');
        var caja = document.querySelector('[data-buscador-caja]');
        var contenedorPills = document.querySelector('[data-buscador-pills]');
        var input = document.getElementById('q');
        var btnLimpiar = document.querySelector('[data-buscador-limpiar]');
        var hiddenQContenedor = document.querySelector('[data-buscador-hidden-q]');
        var resultadosContenedor = document.querySelector('[data-catalogo-resultados]');
        var formVehiculo = document.getElementById('form-filtro-vehiculo');

        if (!form || !contenedorPills || !input || !resultadosContenedor) return;

        var controladorAbort = null;

        // Foco en input al hacer clic en cualquier parte de la caja
        if (caja) {
            caja.addEventListener('click', function (e) {
                if (e.target !== btnLimpiar && !e.target.closest('[data-buscador-limpiar]') &&
                    !e.target.closest('[data-quitar-pill]') && !e.target.closest('.buscador-btn-submit')) {
                    input.focus();
                }
            });
        }

        function obtenerTerminos() {
            var pills = contenedorPills.querySelectorAll('.buscador-pill');
            var terminos = [];
            pills.forEach(function (pill) {
                var t = pill.getAttribute('data-termino') || '';
                if (!t) {
                    var textoEl = pill.querySelector('.buscador-pill-texto');
                    t = textoEl ? textoEl.textContent : '';
                }
                t = t.trim();
                if (t !== '') terminos.push(t);
            });
            return terminos;
        }

        function actualizarUI() {
            var terminos = obtenerTerminos();
            if (btnLimpiar) {
                btnLimpiar.classList.toggle('oculto', terminos.length === 0 && input.value.trim() === '');
            }
            if (terminos.length === 0) {
                input.placeholder = 'Buscar por código, nombre o descripción (Espacio para agregar filtro)…';
            } else {
                input.placeholder = 'Escriba y presione espacio…';
            }

            sincronizarHiddenInputs(terminos);
        }

        function sincronizarHiddenInputs(terminos) {
            if (hiddenQContenedor) {
                hiddenQContenedor.innerHTML = '';
                terminos.forEach(function (t) {
                    var hid = document.createElement('input');
                    hid.type = 'hidden';
                    hid.name = 'q[]';
                    hid.value = t;
                    hiddenQContenedor.appendChild(hid);
                });
            }

            if (formVehiculo) {
                var viejosHiddens = formVehiculo.querySelectorAll('input[name="q[]"]');
                viejosHiddens.forEach(function (h) { h.remove(); });
                terminos.forEach(function (t) {
                    var hid = document.createElement('input');
                    hid.type = 'hidden';
                    hid.name = 'q[]';
                    hid.value = t;
                    formVehiculo.appendChild(hid);
                });
            }
        }

        function crearPill(termino) {
            termino = termino.trim();
            if (termino === '') return false;

            // Evitar duplicados insensibles a mayúsculas
            var existentes = obtenerTerminos();
            var yaExiste = existentes.some(function (t) {
                return t.toLowerCase() === termino.toLowerCase();
            });
            if (yaExiste) {
                input.value = '';
                return false;
            }

            var pill = document.createElement('span');
            pill.className = 'buscador-pill';
            pill.setAttribute('data-termino', termino);

            var texto = document.createElement('span');
            texto.className = 'buscador-pill-texto';
            texto.textContent = termino;

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'buscador-pill-quitar';
            btn.setAttribute('data-quitar-pill', '');
            btn.setAttribute('aria-label', 'Quitar ' + termino);
            btn.innerHTML = '&times;';

            pill.appendChild(texto);
            pill.appendChild(btn);

            contenedorPills.insertBefore(pill, input);
            actualizarUI();
            return true;
        }

        function construirUrlBusqueda(urlBase) {
            var url;
            if (urlBase) {
                url = new URL(urlBase, window.location.origin);
            } else {
                url = new URL(form.getAttribute('action') || '/catalogo', window.location.origin);
            }

            var terminos = obtenerTerminos();
            url.searchParams.delete('q[]');
            url.searchParams.delete('q');

            terminos.forEach(function (t) {
                url.searchParams.append('q[]', t);
            });

            if (!urlBase) {
                var selectMarca = document.querySelector('[data-select-marca]');
                var selectModelo = document.querySelector('[data-select-modelo]');
                var selectSubmodelo = document.querySelector('[data-select-submodelo]');

                var marcaId = selectMarca ? parseInt(selectMarca.value, 10) : 0;
                var modeloId = selectModelo ? parseInt(selectModelo.value, 10) : 0;
                var submodeloId = selectSubmodelo ? parseInt(selectSubmodelo.value, 10) : 0;

                if (marcaId > 0) url.searchParams.set('marca_id', String(marcaId));
                else url.searchParams.delete('marca_id');

                if (modeloId > 0) url.searchParams.set('modelo_id', String(modeloId));
                else url.searchParams.delete('modelo_id');

                if (submodeloId > 0) url.searchParams.set('submodelo_id', String(submodeloId));
                else url.searchParams.delete('submodelo_id');
            }

            return url.toString();
        }

        function sincronizarSelectsVehiculoDesdeUrl(urlStr) {
            try {
                var u = new URL(urlStr, window.location.origin);
                var selectMarca = document.querySelector('[data-select-marca]');
                var selectModelo = document.querySelector('[data-select-modelo]');
                var selectSubmodelo = document.querySelector('[data-select-submodelo]');
                var marcaVal = u.searchParams.get('marca_id') || '0';
                var modeloVal = u.searchParams.get('modelo_id') || '0';
                var submodeloVal = u.searchParams.get('submodelo_id') || '0';

                if (selectMarca) selectMarca.value = marcaVal;
                if (selectModelo) {
                    selectModelo.value = modeloVal;
                    selectModelo.disabled = (parseInt(marcaVal, 10) <= 0);
                }
                if (selectSubmodelo) {
                    selectSubmodelo.value = submodeloVal;
                    selectSubmodelo.disabled = (parseInt(modeloVal, 10) <= 0);
                }
            } catch (e) { }
        }

        function ejecutarBusquedaDinamica(urlDestino) {
            var urlFinal = urlDestino ? urlDestino : construirUrlBusqueda();

            if (controladorAbort) {
                controladorAbort.abort();
            }
            controladorAbort = new AbortController();

            resultadosContenedor.classList.add('cargando');

            fetch(urlFinal, {
                signal: controladorAbort.signal,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (respuesta) {
                    if (!respuesta.ok) throw new Error('Error en la respuesta del servidor');
                    return respuesta.text();
                })
                .then(function (html) {
                    var parser = new DOMParser();
                    var nuevoDoc = parser.parseFromString(html, 'text/html');
                    var nuevosResultados = nuevoDoc.querySelector('[data-catalogo-resultados]');

                    if (nuevosResultados) {
                        resultadosContenedor.innerHTML = nuevosResultados.innerHTML;

                        if (window.history && window.history.replaceState) {
                            window.history.replaceState(null, '', urlFinal);
                        }

                        aplicarModoVista();

                        if (ultimoEstadoCarrito) {
                            sincronizarCantidadesCatalogo(ultimoEstadoCarrito);
                        }
                    }
                    resultadosContenedor.classList.remove('cargando');
                })
                .catch(function (error) {
                    if (error.name === 'AbortError') return;
                    resultadosContenedor.classList.remove('cargando');
                    mostrarAviso('No se pudo actualizar el catálogo. Intente nuevamente.', 'error');
                });
        }

        // Teclado en el input
        input.addEventListener('keydown', function (e) {
            // Tecla ESPACIO: agregar palabra como pill y ejecutar búsqueda
            if (e.key === ' ' || e.keyCode === 32) {
                var palabra = input.value.trim();
                e.preventDefault();
                if (palabra !== '') {
                    crearPill(palabra);
                    input.value = '';
                    ejecutarBusquedaDinamica();
                }
                return;
            }

            // Tecla BACKSPACE: si el cursor está al inicio pegado al último pill y el input está vacío
            if (e.key === 'Backspace' || e.keyCode === 8) {
                if (input.selectionStart === 0 && input.selectionEnd === 0 && input.value === '') {
                    var pills = contenedorPills.querySelectorAll('.buscador-pill');
                    if (pills.length > 0) {
                        e.preventDefault();
                        var ultimaPill = pills[pills.length - 1];
                        ultimaPill.remove();
                        actualizarUI();
                        ejecutarBusquedaDinamica();
                    }
                }
                return;
            }

            // Tecla ENTER: convertir texto pendiente a pill y filtrar
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                var resto = input.value.trim();
                if (resto !== '') {
                    crearPill(resto);
                    input.value = '';
                }
                ejecutarBusquedaDinamica();
                return;
            }
        });

        // Soporte adicional en evento input para teclados móviles / autocompletado
        input.addEventListener('input', function () {
            var val = input.value;
            if (val.indexOf(' ') !== -1) {
                var partes = val.split(/\s+/);
                var terminaEnEspacio = /\s$/.test(val);
                var huboPill = false;

                if (terminaEnEspacio) {
                    partes.forEach(function (p) {
                        if (crearPill(p)) huboPill = true;
                    });
                    input.value = '';
                } else if (partes.length > 1) {
                    for (var i = 0; i < partes.length - 1; i++) {
                        if (crearPill(partes[i])) huboPill = true;
                    }
                    input.value = partes[partes.length - 1];
                }

                if (huboPill) {
                    ejecutarBusquedaDinamica();
                }
            }
            actualizarUI();
        });

        // Pegado de texto con múltiples palabras
        input.addEventListener('paste', function (e) {
            var textoPegado = (e.clipboardData || window.clipboardData).getData('text') || '';
            if (textoPegado.indexOf(' ') !== -1 || textoPegado.indexOf('\n') !== -1) {
                e.preventDefault();
                var partes = textoPegado.split(/\s+/);
                var huboAgregado = false;
                partes.forEach(function (parte) {
                    if (crearPill(parte)) huboAgregado = true;
                });
                input.value = '';
                if (huboAgregado) {
                    ejecutarBusquedaDinamica();
                }
            }
        });

        // Quitar pill individual con clic en ×
        contenedorPills.addEventListener('click', function (e) {
            var btnQuitar = e.target.closest('[data-quitar-pill]');
            if (!btnQuitar) return;
            var pill = btnQuitar.closest('.buscador-pill');
            if (pill) {
                pill.remove();
                actualizarUI();
                ejecutarBusquedaDinamica();
                input.focus();
            }
        });

        // Botón limpiar todos los filtros de texto
        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', function () {
                var pills = contenedorPills.querySelectorAll('.buscador-pill');
                pills.forEach(function (p) { p.remove(); });
                input.value = '';
                actualizarUI();
                ejecutarBusquedaDinamica();
                input.focus();
            });
        }

        // Form submit
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var remanente = input.value.trim();
            if (remanente !== '') {
                crearPill(remanente);
                input.value = '';
            }
            ejecutarBusquedaDinamica();
        });

        // Clics en enlaces de paginación y etiquetas de vehículo dentro de [data-catalogo-resultados]
        document.addEventListener('click', function (e) {
            var enlacePaginacion = e.target.closest('[data-catalogo-resultados] .paginador a');
            if (enlacePaginacion && enlacePaginacion.href) {
                e.preventDefault();
                ejecutarBusquedaDinamica(enlacePaginacion.href);
                resultadosContenedor.scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }

            var enlaceVehiculo = e.target.closest('[data-catalogo-resultados] .pill-vehiculo a, [data-catalogo-resultados] a.pill-limpiar');
            if (enlaceVehiculo && enlaceVehiculo.href) {
                e.preventDefault();
                sincronizarSelectsVehiculoDesdeUrl(enlaceVehiculo.href);
                ejecutarBusquedaDinamica(enlaceVehiculo.href);
                return;
            }
        });

        // Interceptar formulario de vehículo lateral para filtrar por AJAX sin recargar página
        if (formVehiculo) {
            formVehiculo.addEventListener('submit', function (e) {
                e.preventDefault();
                ejecutarBusquedaDinamica();
            });
        }
    }

    /* ── Pestañas y acordeón de remitos en detalle de pedido ─────────── */

    function inicializarDetallePedido() {
        document.addEventListener('click', function (e) {
            // Cambio de pestañas (Productos / Remitos)
            var tabBtn = e.target.closest('[data-tab-btn]');
            if (tabBtn) {
                e.preventDefault();
                var target = tabBtn.getAttribute('data-tab-btn');
                var todosLosBotones = document.querySelectorAll('[data-tab-btn]');
                var todosLosPaneles = document.querySelectorAll('.pedido-tab-panel');

                todosLosBotones.forEach(function (btn) {
                    btn.classList.remove('activo');
                    btn.setAttribute('aria-selected', 'false');
                });
                todosLosPaneles.forEach(function (panel) {
                    panel.classList.add('oculto');
                });

                tabBtn.classList.add('activo');
                tabBtn.setAttribute('aria-selected', 'true');

                var panelActivo = document.getElementById(target) || document.getElementById('tab-' + target);
                if (panelActivo) {
                    panelActivo.classList.remove('oculto');
                }
                return;
            }

            // Colapsar/Expandir tarjeta de remito
            var remitoToggle = e.target.closest('[data-remito-toggle]');
            if (remitoToggle) {
                e.preventDefault();
                var card = remitoToggle.closest('[data-remito-card]');
                if (card) {
                    var colapsado = card.classList.toggle('colapsado');
                    remitoToggle.setAttribute('aria-expanded', colapsado ? 'false' : 'true');
                }
                return;
            }
        });
    }

    inicializarModoVista();
    inicializarFiltrosVehiculo();
    inicializarBuscadorDinamico();
    inicializarDetallePedido();
})();
