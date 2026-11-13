<?php
/**
 * Clasificación de las líneas del extracto con las reglas del banco (gestion__bancos_reglas).
 * Reglas ordenadas por prioridad (menor primero) y regla_id; gana la primera que coincide.
 * tipo_coincidencia: CONTIENE / EMPIEZA / IGUAL (sin distinguir mayúsculas ni espacios repetidos) / REGEX (bco_regex_coincide).
 * IGUAL es la regla aprendida de una asignación manual (origen APRENDIDA, bco_regla_aprender en bancos_config_service.php).
 * aplica_a: DEBITO (importe < 0) / CREDITO (importe > 0) / AMBOS.
 * Sin regla que coincida -> PENDIENTE (no entra a ningún flujo hasta que se le asigne concepto a mano).
 */

require_once __DIR__ . '/extracto_parser.php';

const BCO_TIPOS_COINCIDENCIA = ['CONTIENE', 'EMPIEZA', 'REGEX', 'IGUAL'];
const BCO_APLICA_A = ['DEBITO', 'CREDITO', 'AMBOS'];

/** Error de validación de una regla, o null. */
function bco_regla_validar(string $tipo, string $patron, string $aplicaA): ?string
{
    if (!in_array($tipo, BCO_TIPOS_COINCIDENCIA, true)) return 'Tipo de coincidencia no válido.';
    if (!in_array($aplicaA, BCO_APLICA_A, true)) return 'Elegí si la regla aplica a débitos, créditos o ambos.';
    $patron = trim($patron);
    if ($patron === '') return 'Ingresá el texto a buscar.';
    $max = $tipo === 'IGUAL' ? 300 : 200;
    if (mb_strlen($patron) > $max) return "El texto no puede superar los $max caracteres.";
    return $tipo === 'REGEX' ? bco_regex_validar($patron) : null;
}

/** Devuelve la regla que coincide con la línea, o null. $reglas ya ordenadas (bco_reglas_ordenar). */
function bco_regla_para(array $linea, array $reglas): ?array
{
    $texto = bco_texto_normalizado((string)$linea['descripcion']);
    $importe = (float)$linea['importe'];
    foreach ($reglas as $r) {
        if ($r['aplica_a'] === 'DEBITO' && $importe >= 0) continue;
        if ($r['aplica_a'] === 'CREDITO' && $importe <= 0) continue;
        switch ($r['tipo_coincidencia']) {
            case 'CONTIENE':
                $ok = strpos($texto, bco_texto_normalizado((string)$r['patron'])) !== false;
                break;
            case 'EMPIEZA':
                $ok = strpos($texto, bco_texto_normalizado((string)$r['patron'])) === 0;
                break;
            case 'IGUAL':
                $ok = $texto === bco_texto_normalizado((string)$r['patron']);
                break;
            default:
                $ok = bco_regex_coincide((string)$r['patron'], (string)$linea['descripcion']) === true;
        }
        if ($ok) return $r;
    }
    return null;
}

/**
 * Asigna concepto_id, regla_id, concepto_origen y estado a cada línea (sin tocar las que ya tienen concepto MANUAL).
 * @return array las líneas con esas claves
 */
function bco_reglas_clasificar(array $lineas, array $reglas): array
{
    $reglas = bco_reglas_ordenar($reglas);
    foreach ($lineas as &$l) {
        if (($l['concepto_origen'] ?? null) === 'MANUAL') continue;
        $r = bco_regla_para($l, $reglas);
        $l['concepto_id'] = $r ? (int)$r['concepto_id'] : null;
        $l['regla_id'] = $r ? (int)$r['regla_id'] : null;
        $l['concepto_origen'] = $r ? 'REGLA' : null;
        $l['estado'] = $r ? 'CLASIFICADA' : 'PENDIENTE';
    }
    unset($l);
    return $lineas;
}

function bco_reglas_ordenar(array $reglas): array
{
    usort($reglas, static fn($a, $b) => [(int)$a['prioridad'], (int)$a['regla_id']] <=> [(int)$b['prioridad'], (int)$b['regla_id']]);
    return $reglas;
}

function bco_texto_normalizado(string $t): string
{
    return preg_replace('/\s+/u', ' ', trim(mb_strtoupper($t)));
}
