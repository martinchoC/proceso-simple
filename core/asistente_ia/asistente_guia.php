<?php
/**
 * Guía funcional del ERP para el Asistente IA (herramienta consultar_guia_sistema).
 * Permite responder dudas de operación ("¿cómo pago un sueldo?", "¿cómo cargo un FCI?",
 * "¿cómo imputo un recibo a cuenta?") con los circuitos reales del sistema.
 */

function asis_guia_temas(): array
{
    return [
        'general' => "Temas disponibles en la guía del sistema: sueldos, impuestos, socios, fci, cobranzas, bancos, ventas, contabilidad. "
            . "Llamá a consultar_guia_sistema con el tema específico para ver el circuito completo paso a paso.",

        'sueldos' => "Pago de sueldos, aguinaldo (SAC), cargas sociales (F.931) y sindicato:\n"
            . "1. No se usa Solicitud de Fondos (eso es para gastos a rendir / fondos fijos). Se usa el circuito Obligaciones de Pago -> Órdenes de Pago.\n"
            . "2. El empleado debe estar cargado en Entidades con rol Empleado (EMP); los organismos (ARCA, sindicato) con rol Organismo (ORG).\n"
            . "3. En Tesorería > Obligaciones de Pago (obligaciones_pago.php) se carga la obligación con tipo SUELDO, SAC, CARGAS_SOC, SINDICATO o ANT_SUELDO (anticipo de sueldo).\n"
            . "4. Al confirmar (o aprobar si hay regla de aprobación) se genera el devengamiento contable (Gasto a Pasivo) y el comprobante OBL (o SOL_ANT para anticipos).\n"
            . "5. Luego se paga desde Órdenes de Pago (pagos.php), lo que genera el movimiento de egreso en banco/caja listo para conciliar con el extracto bancario.\n"
            . "6. Si hubo un anticipo de sueldo (ANT_SUELDO), se descuenta aplicando el anticipo contra el sueldo en Obligaciones de Pago > Aplicar anticipos.",

        'impuestos' => "Liquidación y pago de impuestos:\n"
            . "1. Impuestos liquidados por el sistema (IVA en Posición Mensual de IVA, Percepciones IIBB, Retenciones, IIBB mensual): "
            . "al liquidar la posición con saldo a pagar, el sistema genera automáticamente la obligación de pago (IMP_IVA, IMP_PER_IIBB, IMP_RET, comprobante I_LIQ). "
            . "Solo resta pagarla con una Orden de Pago (pagos.php) al organismo.\n"
            . "2. Otros impuestos, tasas, anticipos de Ganancias o planes de pago: se cargan manualmente en Obligaciones de Pago con tipo IMP_VARIOS y luego se pagan con Orden de Pago.",

        'socios' => "Anticipos / retiros de socios y aportes de socios:\n"
            . "1. Plan de cuentas: crear bajo Otros Créditos (1.1.03) una cuenta suma 'Cuenta Particular Socios' (no imputable) y una subcuenta imputable por cada socio.\n"
            . "2. Retiros / anticipos a cuenta: crear el tipo de obligación ANT_SOCIO (clase ANTICIPO, comprobante SOL_ANT). "
            . "Cada socio debe existir en Entidades (con rol EMP o sin restricción de rol). "
            . "Se carga el retiro en Obligaciones de Pago eligiendo la cuenta particular del socio y se paga con Orden de Pago (Debe: Cuenta Particular Socio / Haber: Banco, más egreso en tesorería para conciliar).\n"
            . "3. Aportes de socios (ingreso de dinero al banco): registrar un movimiento de tesorería de tipo INGRESO en el banco con contrapartida en la Cuenta Particular del socio o Capital Social (3.1.01). "
            . "Así queda tanto el asiento contable como el ingreso en tesorería listo para conciliar con el extracto bancario.\n"
            . "4. Al cierre de ejercicio, cuando se aprueban honorarios o distribución de utilidades, se compensa la Cuenta Particular con un asiento contable.",

        'fci' => "Suscripción, rescate y rendimiento de Fondos Comunes de Inversión (FCI):\n"
            . "1. Dar de alta el FCI como una cuenta propia en Tesorería > Cuentas de Tesorería (tesoreria_cuentas.php), tipo BANCO, vinculada a la cuenta contable 1.1.01.21 Fondos Comunes Inversión.\n"
            . "2. Suscripción: en Movimientos Internos (tesoreria_movimientos.php), cargar una Transferencia desde Cuenta Bancaria -> Cuenta FCI. "
            . "Genera asiento automático (Debe: Fondos Comunes Inversión / Haber: Banco) y el egreso bancario para conciliar.\n"
            . "3. Rescate (retiro): cargar una Transferencia desde Cuenta FCI -> Cuenta Bancaria por el importe total acreditado en el banco (para que concilie exacto con el extracto).\n"
            . "4. Rendimiento / ganancia: cargar un asiento contable manual: Debe Fondos Comunes Inversión / Haber Intereses Ganados (4.2.01), así el saldo contable del FCI coincide con las cuotapartes reales.",

        'cobranzas' => "Cobranzas, recibos a cuenta, notas de crédito y cuenta corriente de clientes:\n"
            . "1. Los recibos (RCX) pueden confirmarse sin imputar a una factura: quedan como saldo a favor (anticipo) del cliente.\n"
            . "2. Para imputar un recibo a cuenta o una Nota de Crédito contra una factura pendiente: ir a Cuenta Corriente de Clientes (clientes_ctacte.php), "
            . "seleccionar el cliente y en el bloque 'Saldo a favor' usar el botón Imputar. "
            . "Esto emite un recibo RCX en $0 que documenta la compensación y genera su asiento contable de compensación en Deudores por Ventas (sin movimiento de fondos).\n"
            . "3. Si luego se anula ese recibo en $0 en Cobranzas, se revierten automáticamente las imputaciones y el asiento.",

        'bancos' => "Extractos bancarios, conciliación y gastos bancarios:\n"
            . "1. Importar el extracto en Extractos Bancarios (bancos_importar.php).\n"
            . "2. En Conciliación Bancaria (bancos_conciliacion.php) se concilian las líneas del banco contra los movimientos de tesorería. "
            . "Para créditos de clientes identificados por CUIT, se puede usar 'Generar recibo' directo desde el extracto.\n"
            . "3. Los gastos del banco (comisiones, impuestos, percepciones) se registran en Gastos Bancarios (bancos_gastos.php).",

        'ventas' => "Ventas (facturas, remitos y copias):\n"
            . "1. Se pueden armar facturas de venta y remitos en Borrador, facturar remitos pendientes (total o parcialmente) y copiar facturas de un período con incremento porcentual y reemplazo de textos.\n"
            . "2. Los borradores se revisan y confirman/autorizan ante ARCA en Facturación (ventas_facturas.php).",

        'contabilidad' => "Contabilidad (plan de cuentas y asientos):\n"
            . "1. El Plan de Cuentas (plan_cuentas.php) organiza cuentas en árbol: las cuentas de suma (es_imputable = 0) agrupan subcuentas y no reciben asientos; solo las imputables (es_imputable = 1) se usan en asientos.\n"
            . "2. Los asientos manuales (cont_asientos.php) requieren que Debe = Haber y que el período contable de la fecha esté abierto.\n"
            . "3. Importante: un asiento manual NO genera movimiento en tesorería; si el movimiento toca Banco o Caja y debe conciliarse con el extracto bancario, conviene cargarlo como Movimiento de Tesorería.",
    ];
}

function asis_h_consultar_guia(array $e): array
{
    $tema = strtolower(trim((string)($e['tema'] ?? 'general')));
    $temas = asis_guia_temas();
    if (!isset($temas[$tema])) {
        return ['tema' => 'general', 'guia' => $temas['general'], 'temas_disponibles' => array_keys($temas)];
    }
    return ['tema' => $tema, 'guia' => $temas[$tema]];
}
