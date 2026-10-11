<?php
/**
 * core/impresion.php — configuración de impresión de comprobantes por empresa.
 *
 * Tabla gestion__empresas_impresion (sql/mantenimiento/20261010_empresas_impresion_01_tabla.sql),
 * administrada en la tarjeta "Impresión de Comprobantes" de Parámetros de Empresa (página 111).
 * Resolución: fila activa del subgrupo del comprobante -> fila general (subgrupo 0) -> defaults.
 * Las plantillas viven en templates/impresion/<documento>_<formato>.php (factura_a4, factura_ticket80).
 */

const IMPRESION_FORMATOS = ['A4' => 'A4 (hoja completa)', 'TICKET80' => 'Ticket 80 mm'];

// Tipografías (gestion__empresas_impresion.fuente): clave => [nombre, font-family, familia de Google Fonts | null]
const IMPRESION_FUENTE_DEFECTO = 'roboto';
const IMPRESION_FUENTES = [
    'roboto'           => ['Roboto', "'Roboto', Arial, sans-serif", 'Roboto:wght@300;400;500;600;700'],
    'open_sans'        => ['Open Sans', "'Open Sans', Arial, sans-serif", 'Open+Sans:wght@300;400;600;700'],
    'lato'             => ['Lato', "'Lato', Arial, sans-serif", 'Lato:wght@300;400;700'],
    'source_sans'      => ['Source Sans 3', "'Source Sans 3', Arial, sans-serif", 'Source+Sans+3:wght@300;400;600;700'],
    'inter'            => ['Inter', "'Inter', Arial, sans-serif", 'Inter:wght@300;400;600;700'],
    'nunito_sans'      => ['Nunito Sans', "'Nunito Sans', Arial, sans-serif", 'Nunito+Sans:wght@300;400;600;700'],
    'roboto_condensed' => ['Roboto Condensed (angosta)', "'Roboto Condensed', Arial, sans-serif", 'Roboto+Condensed:wght@300;400;600;700'],
    'arial'            => ['Arial (del sistema)', 'Arial, Helvetica, sans-serif', null],
];

function impresion_config_defecto(): array
{
    return [
        'empresa_impresion_id' => 0,
        'comprobante_subgrupo_id' => 0,
        'formato' => 'A4',
        'copias_defecto' => 1,
        'mostrar_logo' => 1,
        'mostrar_codigo' => 1,
        'mostrar_bonificacion' => 1,
        'mostrar_sucursal_cliente' => 1,
        'texto_pie' => '',
        'color_acento' => '',
        'fuente' => IMPRESION_FUENTE_DEFECTO,
    ];
}

function impresion_tabla_existe(mysqli $conexion): bool
{
    static $existe = null;
    if ($existe === null) {
        $res = mysqli_query($conexion, "SHOW TABLES LIKE 'gestion__empresas_impresion'");
        $existe = $res && mysqli_num_rows($res) > 0;
    }
    return $existe;
}

/** Normaliza una fila (o datos de formulario) a valores válidos. */
function impresion_normalizar(array $fila): array
{
    $cfg = array_merge(impresion_config_defecto(), array_intersect_key($fila, impresion_config_defecto()));
    $cfg['empresa_impresion_id'] = intval($cfg['empresa_impresion_id']);
    $cfg['comprobante_subgrupo_id'] = max(0, intval($cfg['comprobante_subgrupo_id']));
    $cfg['formato'] = isset(IMPRESION_FORMATOS[$cfg['formato']]) ? $cfg['formato'] : 'A4';
    $cfg['copias_defecto'] = max(1, min(3, intval($cfg['copias_defecto'])));
    foreach (['mostrar_logo', 'mostrar_codigo', 'mostrar_bonificacion', 'mostrar_sucursal_cliente'] as $k) {
        $cfg[$k] = !empty($cfg[$k]) ? 1 : 0;
    }
    $cfg['texto_pie'] = mb_substr(trim((string) $cfg['texto_pie']), 0, 1000);
    $cfg['color_acento'] = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $cfg['color_acento']) ? strtolower($cfg['color_acento']) : '';
    $cfg['fuente'] = isset(IMPRESION_FUENTES[$cfg['fuente']]) ? $cfg['fuente'] : IMPRESION_FUENTE_DEFECTO;
    return $cfg;
}

/** <link> de Google Fonts (vacío para fuentes del sistema) y font-family CSS de la tipografía configurada. */
function impresion_fuente(array $cfg): array
{
    $f = IMPRESION_FUENTES[$cfg['fuente'] ?? ''] ?? IMPRESION_FUENTES[IMPRESION_FUENTE_DEFECTO];
    $link = $f[2] ? '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=' . $f[2] . '&display=swap">' : '';
    return ['link' => $link, 'family' => $f[1], 'nombre' => $f[0]];
}

/** Configuración que aplica a un comprobante de la empresa (subgrupo del tipo de comprobante). */
function impresion_config(mysqli $conexion, int $empresa_id, int $comprobante_subgrupo_id = 0): array
{
    if (!impresion_tabla_existe($conexion)) {
        return impresion_config_defecto();
    }
    $stmt = mysqli_prepare($conexion, "SELECT * FROM gestion__empresas_impresion
                                       WHERE empresa_id = ? AND comprobante_subgrupo_id IN (0, ?) AND tabla_estado_registro_id = 1
                                       ORDER BY comprobante_subgrupo_id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'ii', $empresa_id, $comprobante_subgrupo_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $fila ? impresion_normalizar($fila) : impresion_config_defecto();
}

/** Ruta de la plantilla del documento en el formato configurado (A4 si no existe la del formato). */
function impresion_plantilla(string $documento, string $formato): string
{
    $base = __DIR__ . '/../templates/impresion/' . $documento . '_';
    $archivo = $base . strtolower($formato) . '.php';
    return is_file($archivo) ? $archivo : $base . 'a4.php';
}
