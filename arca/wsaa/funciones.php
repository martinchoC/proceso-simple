<?php

// ============================================================
// GENERAR LOGIN TICKET REQUEST
// ============================================================

function generarTRA($servicio)
{
    $ahora = time();

    $generationTime = gmdate(
        'Y-m-d\TH:i:s\Z',
        $ahora - 600
    );

    $expirationTime = gmdate(
        'Y-m-d\TH:i:s\Z',
        $ahora + 600
    );

    $uniqueId = $ahora;

    $xml = '<?xml version="1.0" encoding="UTF-8"?>';

    $xml .= '<loginTicketRequest version="1.0">';

    $xml .= '<header>';

    $xml .= '<uniqueId>' . $uniqueId . '</uniqueId>';

    $xml .= '<generationTime>' .
            $generationTime .
            '</generationTime>';

    $xml .= '<expirationTime>' .
            $expirationTime .
            '</expirationTime>';

    $xml .= '</header>';

    $xml .= '<service>' .
            htmlspecialchars(
                $servicio,
                ENT_XML1,
                'UTF-8'
            ) .
            '</service>';

    $xml .= '</loginTicketRequest>';

    return $xml;
}


// ============================================================
// FIRMAR TRA CON CERTIFICADO
// ============================================================

function firmarTRA(
    $xml,
    $certificado,
    $clave_privada
)
{
    // ==========================================
    // VERIFICAR ARCHIVOS
    // ==========================================

    if (!file_exists($certificado)) {

        die(
            'No existe el certificado: ' .
            $certificado
        );
    }

    if (!file_exists($clave_privada)) {

        die(
            'No existe la clave privada: ' .
            $clave_privada
        );
    }


    // ==========================================
    // LEER CERTIFICADO Y CLAVE
    // ==========================================

    $contenidoCertificado =
        file_get_contents($certificado);

    $contenidoClave =
        file_get_contents($clave_privada);


    if ($contenidoCertificado === false) {

        die(
            'No se pudo leer el certificado.'
        );
    }

    if ($contenidoClave === false) {

        die(
            'No se pudo leer la clave privada.'
        );
    }


    // ==========================================
    // COMPROBAR CLAVE PRIVADA
    // ==========================================

    $clave =
        openssl_pkey_get_private(
            $contenidoClave
        );


    if ($clave === false) {

        $errores = '';

        while (
            $error = openssl_error_string()
        ) {

            $errores .=
                $error . "\n";
        }

        die(
            '<h3>PHP no pudo interpretar la clave privada</h3>' .
            '<pre>' .
            htmlspecialchars($errores) .
            '</pre>'
        );
    }

    echo 'Clave privada: OK<br>';


    // ==========================================
    // COMPROBAR CERTIFICADO
    // ==========================================

    $cert =
        openssl_x509_read(
            $contenidoCertificado
        );


    if ($cert === false) {

        $errores = '';

        while (
            $error = openssl_error_string()
        ) {

            $errores .=
                $error . "\n";
        }

        die(
            '<h3>PHP no pudo interpretar el certificado</h3>' .
            '<pre>' .
            htmlspecialchars($errores) .
            '</pre>'
        );
    }

    echo 'Certificado: OK<br>';


    // ==========================================
    // ARCHIVOS TEMPORALES
    // ==========================================

    $archivoTRA =
        tempnam(
            sys_get_temp_dir(),
            'arca_tra_'
        );

    $archivoCMS =
        tempnam(
            sys_get_temp_dir(),
            'arca_cms_'
        );


    if (
        $archivoTRA === false ||
        $archivoCMS === false
    ) {

        die(
            'No se pudieron crear los archivos temporales.'
        );
    }


    // ==========================================
    // GUARDAR TRA
    // ==========================================

    file_put_contents(
        $archivoTRA,
        $xml
    );


    // ==========================================
    // FIRMAR CMS
    // ==========================================
    //
    // IMPORTANTE:
    //
    // ARCA requiere CMS ATTACHED.
    //
    // Por eso NO usamos:
    //
    // PKCS7_DETACHED
    //
    // El parámetro 6 queda en 0.
    //
    // Esto equivale al !PKCS7_DETACHED
    // del ejemplo oficial de ARCA.
    //
    // ==========================================

    $resultado =
        openssl_pkcs7_sign(
            $archivoTRA,
            $archivoCMS,
            $contenidoCertificado,
            array(
                $contenidoClave,
                ''
            ),
            array(),
            0
        );


    if (!$resultado) {

        $errores = '';

        while (
            $error = openssl_error_string()
        ) {

            $errores .=
                $error . "\n";
        }

        @unlink($archivoTRA);
        @unlink($archivoCMS);

        die(
            '<h3>Error al firmar el TRA</h3>' .
            '<pre>' .
            htmlspecialchars($errores) .
            '</pre>'
        );
    }


    echo 'Firma PKCS#7: OK<br>';


    // ==========================================
    // LEER CMS
    // ==========================================

    $contenido =
        file_get_contents(
            $archivoCMS
        );


    if ($contenido === false) {

        die(
            'No se pudo leer el CMS generado.'
        );
    }


    // ==========================================
    // EXTRAER CMS
    // ==========================================
    //
    // openssl_pkcs7_sign genera encabezados MIME.
    //
    // El ejemplo oficial de ARCA elimina
    // los primeros encabezados y conserva
    // el contenido Base64 del CMS.
    //
    // ==========================================

    $lineas =
        preg_split(
            "/\r\n|\n|\r/",
            $contenido
        );


    $cmsBase64 = '';

    $cantidadLineas =
        count($lineas);


    /*
     * Buscamos el comienzo del contenido
     * después de los encabezados MIME.
     *
     * En la salida estándar de OpenSSL:
     *
     * línea 1 -> MIME-Version
     * línea 2 -> Content-Disposition
     * línea 3 -> Content-Type
     * línea 4 -> Content-Transfer-Encoding
     * línea 5 -> Base64
     *
     * ARCA muestra en su ejemplo PHP
     * que se deben ignorar las primeras
     * cuatro líneas.
     */

    for (
        $i = 4;
        $i < $cantidadLineas;
        $i++
    ) {

        $cmsBase64 .=
            trim($lineas[$i]);
    }


    if ($cmsBase64 == '') {

        @unlink($archivoTRA);
        @unlink($archivoCMS);

        die(
            'No se pudo obtener el contenido Base64 del CMS.'
        );
    }


    // ==========================================
    // VALIDAR BASE64
    // ==========================================

    $cmsBinario =
        base64_decode(
            $cmsBase64,
            true
        );


    if ($cmsBinario === false) {

        @unlink($archivoTRA);
        @unlink($archivoCMS);

        die(
            'El contenido CMS no es Base64 válido.'
        );
    }


    // ==========================================
    // NORMALIZAR BASE64
    // ==========================================

    $cmsBase64 =
        base64_encode(
            $cmsBinario
        );


    // ==========================================
    // ELIMINAR TEMPORALES
    // ==========================================

    @unlink($archivoTRA);
    @unlink($archivoCMS);


    return $cmsBase64;
}


// ============================================================
// ENVIAR CMS AL WSAA
// ============================================================

function consultarWSAA(
    $cmsBase64,
    $url_wsaa
)
{
    $soap =
        '<?xml version="1.0" encoding="UTF-8"?>';

    $soap .=
        '<soapenv:Envelope ' .
        'xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" ' .
        'xmlns:ws="http://ar.gov.afip.dif.facturaelectronica/">';

    $soap .=
        '<soapenv:Header/>';

    $soap .=
        '<soapenv:Body>';

    $soap .=
        '<ws:loginCms>';

    $soap .=
        '<ws:in0>';

    $soap .=
        htmlspecialchars(
            $cmsBase64,
            ENT_XML1,
            'UTF-8'
        );

    $soap .=
        '</ws:in0>';

    $soap .=
        '</ws:loginCms>';

    $soap .=
        '</soapenv:Body>';

    $soap .=
        '</soapenv:Envelope>';


    // --------------------------------------------------------
    // CURL
    // --------------------------------------------------------

    $ch =
        curl_init();


    curl_setopt(
        $ch,
        CURLOPT_URL,
        $url_wsaa
    );

    curl_setopt(
        $ch,
        CURLOPT_POST,
        true
    );

    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        $soap
    );

    curl_setopt(
        $ch,
        CURLOPT_RETURNTRANSFER,
        true
    );


    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array(
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "loginCms"',
            'Content-Length: ' . strlen($soap)
        )
    );


    curl_setopt(
        $ch,
        CURLOPT_SSL_VERIFYPEER,
        true
    );

    curl_setopt(
        $ch,
        CURLOPT_SSL_VERIFYHOST,
        2
    );


    curl_setopt(
        $ch,
        CURLOPT_CONNECTTIMEOUT,
        30
    );

    curl_setopt(
        $ch,
        CURLOPT_TIMEOUT,
        60
    );


    $respuesta =
        curl_exec($ch);


    if ($respuesta === false) {

        $error =
            curl_error($ch);

        curl_close($ch);

        die(
            '<h3>Error CURL</h3>' .
            '<pre>' .
            htmlspecialchars($error) .
            '</pre>'
        );
    }


    $httpCode =
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );


    curl_close($ch);


    return array(
        'http_code' => $httpCode,
        'respuesta' => $respuesta
    );
}


// ============================================================
// EXTRAER TOKEN Y SIGN
// ============================================================

function extraerCredenciales(
    $respuesta
)
{
    libxml_use_internal_errors(true);


    $xml =
        simplexml_load_string(
            $respuesta
        );


    if ($xml === false) {

        return array(
            'ok' => false,
            'error' =>
                'La respuesta de ARCA no pudo interpretarse como XML.',
            'respuesta' => $respuesta
        );
    }


    // --------------------------------------------------------
    // TICKET INTERNO
    // --------------------------------------------------------
    //
    // WSAA devuelve el loginTicketResponse como TEXTO escapado
    // (&lt;token&gt;...) dentro de <loginCmsReturn>, no como nodos
    // XML: hay que parsear ese texto como un segundo documento.
    //
    // --------------------------------------------------------

    $nodosReturn =
        $xml->xpath(
            '//*[local-name()="loginCmsReturn"]'
        );


    if (
        isset($nodosReturn[0]) &&
        trim((string)$nodosReturn[0]) != ''
    ) {

        $xmlInterno =
            simplexml_load_string(
                trim((string)$nodosReturn[0])
            );


        if ($xmlInterno !== false) {

            $xml = $xmlInterno;
        }
    }


    $token = '';
    $sign = '';


    // --------------------------------------------------------
    // TOKEN
    // --------------------------------------------------------

    $nodosToken =
        $xml->xpath(
            '//*[local-name()="token"]'
        );


    if (
        isset($nodosToken[0])
    ) {

        $token =
            (string)$nodosToken[0];
    }


    // --------------------------------------------------------
    // SIGN
    // --------------------------------------------------------

    $nodosSign =
        $xml->xpath(
            '//*[local-name()="sign"]'
        );


    if (
        isset($nodosSign[0])
    ) {

        $sign =
            (string)$nodosSign[0];
    }


    // --------------------------------------------------------
    // SI NO HAY TOKEN/SIGN
    // --------------------------------------------------------

    if (
        $token == '' ||
        $sign == ''
    ) {

        $error =
            'ARCA no devolvió Token y Sign.';


        $nodosFault =
            $xml->xpath(
                '//*[local-name()="Fault"]'
            );


        if (
            isset($nodosFault[0])
        ) {

            $error .=
                "\n\nSOAP Fault:\n" .
                $nodosFault[0]->asXML();
        }


        return array(
            'ok' => false,
            'error' => $error,
            'respuesta' => $respuesta
        );
    }


    // --------------------------------------------------------
    // GENERATION TIME
    // --------------------------------------------------------

    $generationTime = '';

    $nodosGeneration =
        $xml->xpath(
            '//*[local-name()="generationTime"]'
        );


    if (
        isset($nodosGeneration[0])
    ) {

        $generationTime =
            (string)$nodosGeneration[0];
    }


    // --------------------------------------------------------
    // EXPIRATION TIME
    // --------------------------------------------------------

    $expirationTime = '';

    $nodosExpiration =
        $xml->xpath(
            '//*[local-name()="expirationTime"]'
        );


    if (
        isset($nodosExpiration[0])
    ) {

        $expirationTime =
            (string)$nodosExpiration[0];
    }


    return array(
        'ok' => true,
        'token' => $token,
        'sign' => $sign,
        'generationTime' => $generationTime,
        'expirationTime' => $expirationTime,
        'respuesta' => $respuesta
    );
}