<?php
/**
 * Configuración de la lectura de facturas de proveedores desde archivo (core/facturas_lectura/).
 *
 * Copiar este archivo FUERA del webroot (p. ej. /home/<usuario>/config/facturas_lectura.php en producción,
 * C:\xampp\config\facturas_lectura.php en local), completar lo que haga falta y apuntarlo desde el .env de la raíz:
 *
 *     define('FACTURA_LECTURA_CONFIG', '/home/<usuario>/config/facturas_lectura.php');
 *
 * (o con la variable de entorno FACTURA_LECTURA_CONFIG). Todo lo que no se ponga toma el valor por defecto de
 * fl_config() en core/facturas_lectura/FacturaExtractor.php. La API key también puede ir en la variable de entorno
 * ANTHROPIC_API_KEY, que tiene prioridad sobre este archivo. Nunca en el código ni en el navegador.
 *
 * Sin API key la capa de IA no existe (el botón "Reintentar con IA" no aparece) y nada sale del servidor.
 * Con API key, además, cada empresa la tiene que habilitar en Parámetros de Empresa (deshabilitada por defecto,
 * tope de 20 lecturas por día).
 */
return [
    // Tamaño máximo del archivo subido (bytes). Ojo: también limitan upload_max_filesize y post_max_size de php.ini.
    'max_bytes' => 10 * 1024 * 1024,

    // Binarios del servidor. null = se buscan en el PATH (solo Linux). En Windows hay que poner la ruta completa.
    // Sin binarios, el QR, el texto y el OCR los lee el navegador (pdf.js, jsQR, tesseract.js).
    // Debian / Ubuntu: apt install poppler-utils zbar-tools tesseract-ocr tesseract-ocr-spa
    'bin' => [
        'pdftotext' => null,   // '/usr/bin/pdftotext'
        'pdftoppm'  => null,   // '/usr/bin/pdftoppm'   (PDF escaneado -> imagen para zbarimg / tesseract)
        'zbarimg'   => null,   // '/usr/bin/zbarimg'
        'tesseract' => null,   // '/usr/bin/tesseract'
    ],

    'ia' => [
        'proveedor'  => 'gemini',               // 'gemini' (ProveedorIaGemini) o 'claude' (ProveedorIaClaude)
        'modelo'     => 'gemini-3.8-flash',     // 'gemini-3.8-flash' para Gemini, o 'claude-sonnet-5-5' para Claude
        'api_key'    => '',                     // 'AIzaSy...' (GEMINI_API_KEY) o 'sk-ant-...' (ANTHROPIC_API_KEY)
        'esfuerzo'   => 'medium',               // low / medium / high: más esfuerzo, más precisión y más tokens
        'timeout'    => 120,                    // segundos por llamada
        'reintentos' => 1,                      // ante 429 / 5xx / errores de red
        'fallbacks'  => true,                   // reintento del lado de Anthropic si el modelo rechaza el pedido
        'automatica' => true,                   // false = la IA solo con "Leer con IA" / "Reintentar con IA"
        // Para el costo estimado del log (USD por millón de tokens). Actualizar si cambian los precios del modelo.
        'precio_entrada_mtok' => 0.15,
        'precio_salida_mtok'  => 0.60,
    ],

    // Asistente IA (página 139 / 639, core/asistente_ia/): usa la misma clave de 'ia'. Todo opcional.
    'asistente' => [
        'modelo_claude'           => 'claude-opus-5-5',  // con clave sk-ant-...
        'esfuerzo_asistente'      => 'medium',           // low / medium / high (Claude)
        'modelo_gemini_asistente' => '',                 // con clave AIza...; vacío = el mismo de la lectura de facturas
        'precio_entrada_mtok_claude' => 4.00,            // USD por millón de tokens, para el costo estimado del registro
        'precio_salida_mtok_claude'  => 20.00,
    ],

    // cron/facturas_proveedores_lectura.php: una carpeta de entrada por empresa (fuera del webroot). Adentro se crean
    // procesados/ (facturas cargadas en Borrador) y no_procesados/ (con un <archivo>.motivo.txt).
    // usuario_id: autor de las facturas; necesita perfil en la empresa con "Agregar" en Facturas de proveedores.
    // sucursal_id: 0 = la primera sucursal a la que el usuario tiene acceso.
    'cron' => [
        'empresas' => [
            // 6 => ['directorio' => '/home/<usuario>/facturas_entrada/empresa_6', 'usuario_id' => 12, 'sucursal_id' => 0],
        ],
    ],
];
