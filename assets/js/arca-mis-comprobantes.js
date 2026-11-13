/**
 * Lectura de "Mis Comprobantes" de ARCA (Emitidos y Recibidos, .xlsx / .xls / .csv) en el navegador.
 * Global ArcaMC. Necesita XLSX (assets/js/xlsx-0.18.5/xlsx.full.min.js) para .xlsx / .xls; el .csv lo lee solo.
 *
 * Solo arma las filas: los importes y los tipos los interpreta y valida el servidor.
 *   - Recibidos: el formato de fpPrevisualizarImportacionArca / fpImportarComprobantesArca (celdas crudas;
 *     el servidor convierte los importes con fpArcaNumero).
 *   - Emitidos: el formato de vfiAnalizarArchivo / vfiImportar (importes ya numéricos, neto e IVA por alícuota).
 * Origen: mapearColumnasArca / leerFilaArca de facturas_proveedores.js (que ahora delegan acá).
 *
 * Uso: ArcaMC.leerArchivo(file).then(function (r) { r.sentido, r.cuit, r.filas }).
 *      ArcaMC.interpretar(filasCrudas, nombreArchivo) hace lo mismo sobre un array de filas (pruebas con node).
 */
(function (global) {
    'use strict';

    // Columnas por nombre exacto (sin tildes, puntos, BOM ni mayúsculas). Sirve para el formato actual (neto e IVA
    // por alícuota, "Neto Gravado Total", "Total IVA", "Imp. Total") y para el anterior ("Imp. Neto Gravado", "IVA").
    function normalizarEncabezado(h) {
        return String(h || '').replace(/^﻿/, '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
            .replace(/\./g, ' ').replace(/\s+/g, ' ').trim();
    }

    var NOMBRES = {
        fecha: ['fecha', 'fecha de emision'],
        tipo: ['tipo', 'tipo de comprobante'],
        pv: ['punto de venta'],
        nro: ['numero desde', 'numero', 'nro'],
        nro_hasta: ['numero hasta'],
        cae: ['cod autorizacion', 'cae'],
        cuit: ['nro doc emisor', 'cuit emisor'],
        razon: ['denominacion emisor'],
        tipo_doc_receptor: ['tipo doc receptor'],
        cuit_receptor: ['nro doc receptor'],
        razon_receptor: ['denominacion receptor'],
        tipo_cambio: ['tipo cambio', 'tipo de cambio'],
        moneda: ['moneda'],
        neto: ['neto gravado total', 'imp neto gravado'],
        no_gravado: ['neto no gravado', 'imp neto no gravado'],
        exento: ['op exentas', 'imp op exentas'],
        otros: ['otros tributos'],
        iva: ['total iva', 'iva'],
        total: ['imp total']
    };

    function mapearColumnas(headers) {
        var map = { alicuotas: {} };
        Object.keys(NOMBRES).forEach(function (k) { map[k] = -1; });
        (headers || []).forEach(function (h, idx) {
            var norm = normalizarEncabezado(h);
            if (!norm) return;
            var m = norm.match(/^(neto grav iva|iva) (\d+(?:,\d+)?)%$/);
            if (m) {
                var pct = parseFloat(m[2].replace(',', '.'));
                map.alicuotas[pct] = map.alicuotas[pct] || { neto: -1, iva: -1 };
                map.alicuotas[pct][m[1] === 'iva' ? 'iva' : 'neto'] = idx;
                return;
            }
            Object.keys(NOMBRES).forEach(function (k) {
                if (map[k] === -1 && NOMBRES[k].indexOf(norm) !== -1) map[k] = idx;
            });
        });
        return map;
    }

    function celda(row, idx) {
        return idx === -1 || row[idx] === undefined || row[idx] === null ? '' : row[idx];
    }

    // Fecha dd/mm/aaaa; si Excel la guardó como fecha, viene como número de serie
    function fecha(v) {
        if (typeof v === 'number' && global.XLSX && global.XLSX.SSF) {
            var d = global.XLSX.SSF.parse_date_code(v);
            if (d) return ('0' + d.d).slice(-2) + '/' + ('0' + d.m).slice(-2) + '/' + d.y;
        }
        return String(v).trim();
    }

    // Importe de ARCA: número del Excel o texto con coma decimal (CSV); vacío = 0
    function numero(v) {
        if (typeof v === 'number') return v;
        var s = String(v || '').replace(/[$\s]/g, '');
        if (s === '') return 0;
        if (s.indexOf(',') !== -1) s = s.replace(/\./g, '').replace(',', '.');
        var n = parseFloat(s);
        return isNaN(n) ? 0 : n;
    }

    // Recibidos: celdas de una fila sin interpretar (lo hace el servidor). null si no es un comprobante.
    function leerFilaRecibidos(row, colMap) {
        if (!row || !row.length) return null;
        var cuit = String(celda(row, colMap.cuit)).replace(/\D/g, '');
        var nro = String(celda(row, colMap.nro)).trim();
        if (!cuit && !nro) return null;   // filas vacías o de totales
        var alicuotas = [];
        Object.keys(colMap.alicuotas).forEach(function (pct) {
            var c = colMap.alicuotas[pct];
            alicuotas.push({ porcentaje: parseFloat(pct), neto: celda(row, c.neto), iva: celda(row, c.iva) });
        });
        return {
            fecha: fecha(celda(row, colMap.fecha)),
            tipo: String(celda(row, colMap.tipo)).trim(),
            pv: celda(row, colMap.pv),
            nro: nro,
            nro_hasta: celda(row, colMap.nro_hasta),
            cae: String(celda(row, colMap.cae)).trim(),
            cuit: cuit,
            razon_social: String(celda(row, colMap.razon)).trim(),
            cuit_receptor: String(celda(row, colMap.cuit_receptor)).replace(/\D/g, ''),
            tipo_cambio: celda(row, colMap.tipo_cambio),
            moneda: String(celda(row, colMap.moneda)).trim(),
            neto: celda(row, colMap.neto),
            no_gravado: celda(row, colMap.no_gravado),
            exento: celda(row, colMap.exento),
            otros_tributos: celda(row, colMap.otros),
            iva: celda(row, colMap.iva),
            total: celda(row, colMap.total),
            alicuotas: alicuotas
        };
    }

    // Emitidos: formato de vfiAnalizarFila (importes numéricos; neto e IVA por alícuota con clave "21", "10.5", ...)
    function leerFilaEmitidos(row, colMap, indice) {
        if (!row || !row.length) return null;
        var nro = String(celda(row, colMap.nro)).trim();
        var tipo = String(celda(row, colMap.tipo)).trim();
        if (!nro || !/^\s*\d+/.test(tipo)) return null;
        var neto = {}, iva = {};
        Object.keys(colMap.alicuotas).forEach(function (pct) {
            var c = colMap.alicuotas[pct], k = String(parseFloat(pct));
            neto[k] = numero(celda(row, c.neto));
            iva[k] = numero(celda(row, c.iva));
        });
        return {
            indice: indice,
            fecha: fecha(celda(row, colMap.fecha)),
            tipo: tipo,
            tipo_codigo: parseInt(tipo, 10) || 0,
            pv: parseInt(celda(row, colMap.pv), 10) || 0,
            nro_desde: parseInt(nro, 10) || 0,
            nro_hasta: parseInt(celda(row, colMap.nro_hasta), 10) || 0,
            cae: String(celda(row, colMap.cae)).trim(),
            doc_tipo: String(celda(row, colMap.tipo_doc_receptor)).trim(),
            doc_nro: String(celda(row, colMap.cuit_receptor)).replace(/\D/g, ''),
            denominacion: String(celda(row, colMap.razon_receptor)).trim(),
            moneda: String(celda(row, colMap.moneda)).trim(),
            tipo_cambio: numero(celda(row, colMap.tipo_cambio)) || 1,
            neto: neto,
            iva: iva,
            no_gravado: numero(celda(row, colMap.no_gravado)),
            exento: numero(celda(row, colMap.exento)),
            otros_tributos: numero(celda(row, colMap.otros)),
            total: numero(celda(row, colMap.total))
        };
    }

    // CUIT del titular: fila de título del Excel ("... - CUIT 30719136482") o nombre del archivo
    function cuitTitular(filas, headerIdx, nombreArchivo) {
        var textos = [];
        for (var r = 0; r < headerIdx; r++) textos.push((filas[r] || []).join(' '));
        textos.push(String(nombreArchivo || ''));
        for (var i = 0; i < textos.length; i++) {
            var m = textos[i].match(/CUIT\s*:?\s*(\d{2}-?\d{8}-?\d)/i);
            if (m) return m[1].replace(/\D/g, '');
        }
        return '';
    }

    /**
     * Interpreta las filas crudas de la planilla. Devuelve {sentido: 'EMITIDOS'|'RECIBIDOS', cuit, filas}
     * o lanza Error con un mensaje para el usuario.
     */
    function interpretar(filas, nombreArchivo) {
        var headerIdx = -1, colMap = null;
        for (var r = 0; r < Math.min(10, filas.length); r++) {
            var mapa = mapearColumnas(filas[r]);
            if (mapa.fecha !== -1 && mapa.tipo !== -1) { headerIdx = r; colMap = mapa; break; }
        }
        if (headerIdx === -1) {
            throw new Error('No se encontró la fila de encabezados (Fecha, Tipo, Punto de Venta...) de Mis Comprobantes.');
        }
        var sentido = colMap.razon !== -1 ? 'RECIBIDOS' : (colMap.razon_receptor !== -1 ? 'EMITIDOS' : '');
        if (!sentido) throw new Error('No se reconoce si el archivo es de Comprobantes Emitidos o Recibidos.');
        var faltan = ['pv', 'nro', 'total'].concat(sentido === 'RECIBIDOS' ? ['cuit'] : [])
            .filter(function (k) { return colMap[k] === -1; });
        if (faltan.length) throw new Error('Faltan columnas obligatorias: ' + faltan.join(', ') + '.');

        var out = [];
        for (var i = headerIdx + 1; i < filas.length; i++) {
            var f = sentido === 'RECIBIDOS' ? leerFilaRecibidos(filas[i], colMap) : leerFilaEmitidos(filas[i], colMap, out.length);
            if (f) out.push(f);
        }
        if (!out.length) throw new Error('El archivo no tiene comprobantes.');
        var cuit = cuitTitular(filas, headerIdx, nombreArchivo);
        if (!cuit && sentido === 'RECIBIDOS' && out[0].cuit_receptor) cuit = out[0].cuit_receptor;
        return { sentido: sentido, cuit: cuit, filas: out };
    }

    // CSV de ARCA: ';', coma decimal, UTF-8 con BOM. Se lee como texto (XLSX convertiría 02/10 en fecha de EE. UU.).
    function filasCsv(texto) {
        var lineas = String(texto).replace(/^﻿/, '').split(/\r?\n/);
        var sep = (lineas[0] || '').split(';').length >= (lineas[0] || '').split(',').length ? ';' : ',';
        return lineas.filter(function (l) { return l.trim() !== ''; }).map(function (l) {
            var out = [], cur = '', q = false;
            for (var i = 0; i < l.length; i++) {
                var ch = l[i];
                if (ch === '"') { if (q && l[i + 1] === '"') { cur += '"'; i++; } else { q = !q; } }
                else if (ch === sep && !q) { out.push(cur); cur = ''; }
                else { cur += ch; }
            }
            out.push(cur);
            return out;
        });
    }

    function leerArchivo(file) {
        return new Promise(function (resolve, reject) {
            var ext = String(file.name || '').split('.').pop().toLowerCase();
            if (['csv', 'xlsx', 'xls'].indexOf(ext) === -1) {
                reject(new Error('Seleccione el .xlsx o .csv descargado de Mis Comprobantes de ARCA.'));
                return;
            }
            if (ext !== 'csv' && typeof global.XLSX === 'undefined') {
                reject(new Error('No se pudo cargar el lector de Excel (xlsx).'));
                return;
            }
            var reader = new FileReader();
            reader.onload = function (ev) {
                try {
                    var filas;
                    if (ext === 'csv') {
                        filas = filasCsv(new TextDecoder('utf-8').decode(new Uint8Array(ev.target.result)));
                    } else {
                        var wb = global.XLSX.read(new Uint8Array(ev.target.result), { type: 'array' });
                        filas = global.XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], { header: 1, defval: '', raw: true });
                    }
                    resolve(interpretar(filas, file.name));
                } catch (err) {
                    reject(err);
                }
            };
            reader.onerror = function () { reject(new Error('No se pudo leer el archivo.')); };
            reader.readAsArrayBuffer(file);
        });
    }

    global.ArcaMC = {
        leerArchivo: leerArchivo,
        interpretar: interpretar,
        filasCsv: filasCsv,
        mapearColumnas: mapearColumnas,
        leerFilaRecibidos: leerFilaRecibidos,
        fecha: fecha,
        numero: numero
    };
})(typeof window !== 'undefined' ? window : globalThis);
