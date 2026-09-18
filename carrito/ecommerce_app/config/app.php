<?php

declare(strict_types=1);

use App\Support\Env;

/**
 * Entero de configuración que NO admite cero.
 *
 * Env::int() sólo aplica el default cuando la clave falta; un
 * ECOM_COMPROBANTE_TIPO_PEDIDO_ID=0 escrito por el instalador (cuando no pudo
 * detectar el valor) pasaba tal cual y terminaba insertando una FK 0 en
 * gestion__comprobantes / gestion__ventas_pedidos. Para las claves que el
 * negocio fija en 1, un 0 es "sin configurar", no un id válido.
 */
$idFijo = static fn (string $clave, int $porDefecto): int
    => ($v = Env::int($clave, $porDefecto)) > 0 ? $v : $porDefecto;

return [
    'app' => [
        'env'      => Env::get('APP_ENV', 'production'),
        'debug'    => Env::bool('APP_DEBUG', false),
        'url'      => rtrim((string) Env::get('APP_URL', ''), '/'),
        'key'      => Env::get('APP_KEY', ''),
        'timezone' => 'America/Argentina/Buenos_Aires',
    ],

    'db' => [
        'host'    => Env::get('DB_HOST', '127.0.0.1'),
        'port'    => Env::int('DB_PORT', 3306),
        'name'    => Env::get('DB_NAME', ''),
        'user'    => Env::get('DB_USER', ''),
        'pass'    => Env::get('DB_PASS', ''),
        'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
    ],

    'ecom' => [
        'empresa_id'               => Env::int('ECOM_EMPRESA_ID', 0),
        'modulo_id'                => Env::int('ECOM_MODULO_ID', 0),
        'sucursal_id'              => Env::int('ECOM_SUCURSAL_ID', 0),
        // Punto de venta por el que entran los pedidos de la tienda.
        // Estos tres son ids del ERP que el negocio fija en 1: un 0 en el .env
        // es "sin configurar" y se corrige, no se propaga a la base.
        'punto_venta_id'           => $idFijo('ECOM_PUNTO_VENTA_ID', 1),
        'moneda_id'                => $idFijo('ECOM_MONEDA_ID', 1),
        'lista_precio_default_id'  => Env::int('ECOM_LISTA_PRECIO_DEFAULT_ID', 0),
        'comprobante_tipo_pedido'  => $idFijo('ECOM_COMPROBANTE_TIPO_PEDIDO_ID', 1),
        'tabla_origen_pedidos_id'  => Env::int('ECOM_TABLA_ORIGEN_PEDIDOS_ID', 0),
        // Columna de gestion__entidades que guarda el documento del cliente.
        // El usuario del login tiene que coincidir con este valor.
        // Lista blanca: sólo 'cuil' o 'cuit' (el nombre entra al SQL).
        'entidad_doc_columna'      => in_array(
            $doc = strtolower(trim((string) Env::get('ECOM_ENTIDAD_DOC_COLUMNA', 'cuil'))),
            ['cuil', 'cuit'],
            true
        ) ? $doc : 'cuil',
        // Nombres reales del ERP para la regla de acceso web. Se validan como
        // identificadores y se verifican contra information_schema; la
        // aplicación se adapta al esquema, no al revés.
        // Modelo de permisos: 'tienda' (el login ya decidió) o 'erp'
        // (por función, requiere conf__paginas_funciones.codigo_funcion).
        'permisos'                 => Env::get('ECOM_PERMISOS', 'tienda'),
        'tipos_tabla'              => Env::get('ECOM_TIPOS_TABLA', 'gestion__entidades_clientes_tipos'),
        'tipos_pk'                 => Env::get('ECOM_TIPOS_PK', 'entidad_cliente_tipo_id'),
        'tipos_acceso'             => Env::get('ECOM_TIPOS_ACCESO', 'acceso_web'),
        'entidad_tipo_columna'     => Env::get('ECOM_ENTIDAD_TIPO_COLUMNA', 'entidad_tipo_id'),
        'page_size'                => Env::int('CATALOGO_PAGE_SIZE', 24),
        'max_page_size'            => Env::int('CATALOGO_MAX_PAGE_SIZE', 60),
    ],

    'session' => [
        'name'              => Env::get('SESSION_NAME', 'ECOMSID'),
        'idle_minutes'      => Env::int('SESSION_IDLE_MINUTES', 60),
        'absolute_minutes'  => Env::int('SESSION_ABSOLUTE_MINUTES', 480),
        'secure'            => Env::bool('SESSION_SECURE', true),
        'samesite'          => Env::get('SESSION_SAMESITE', 'Lax'),
    ],

    'login' => [
        'max_intentos'     => Env::int('LOGIN_MAX_INTENTOS', 5),
        'ventana_minutos'  => Env::int('LOGIN_VENTANA_MINUTOS', 15),
        'bloqueo_minutos'  => Env::int('LOGIN_BLOQUEO_MINUTOS', 15),
    ],

    // Estados de registro (conf__estados_registros)
    'estados' => [
        'activo'      => 1,
        'inactivo'    => 2,
        'borrador'    => 3,
        'confirmado'  => 5,
        'eliminado'   => 6,
    ],
];
