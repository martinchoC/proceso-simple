<?php

// ==========================================
// CONFIGURACION ARCA - HOMOLOGACION
// ==========================================

$cuit = '20245626151';

$servicio = 'wsfe';

// WSAA HOMOLOGACION
$url_wsaa = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms';

// ==========================================
// CERTIFICADOS
// ==========================================

$certificado = __DIR__ . '/cert/20245626151ARCAHOMOLOGACION.crt';

$clave_privada = __DIR__ . '/cert/20245626151ARCAHOMOLOGACION.key';

// ==========================================
// RESPUESTAS
// ==========================================

$directorio_respuesta = __DIR__ . '/respuesta/';