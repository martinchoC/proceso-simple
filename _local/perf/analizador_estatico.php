<?php
// perf/analizador_estatico.php
// Analizador estático de código PHP/SQL para el inventario de páginas

class AnalizadorEstatico {
    private string $rootPath;
    
    public function __construct(string $rootPath) {
        $this->rootPath = rtrim(str_replace('\\', '/', $rootPath), '/');
    }
    
    /**
     * Analiza una página de pantalla y sus archivos asociados
     */
    public function analizar(string $rutaRelativa): array {
        $fullPath = $this->rootPath . '/' . ltrim($rutaRelativa, '/');
        if (!file_exists($fullPath)) {
            return [
                'error' => "Archivo no encontrado: $rutaRelativa"
            ];
        }
        
        $contenido = file_get_contents($fullPath);
        $kbArchivo = round(filesize($fullPath) / 1024, 2);
        $lineas = count(file($fullPath));
        
        // Archivos asociados típicos en este ERP
        $dir = dirname($fullPath);
        $baseName = basename($fullPath, '.php');
        $modelPath = $dir . '/' . $baseName . '_model.php';
        $ajaxPath = $dir . '/' . $baseName . '_ajax.php';
        if (!file_exists($ajaxPath)) {
            $ajaxPath = $dir . '/' . $baseName . '-ajax.php';
        }
        $jsPath = $dir . '/' . $baseName . '.js';
        
        // 1. Análisis de Includes
        $includes = $this->extraerIncludes($fullPath, $contenido);
        $kbIncludes = 0;
        foreach ($includes as $inc) {
            if (file_exists($inc)) {
                $kbIncludes += filesize($inc);
            }
        }
        $kbIncludes = round($kbIncludes / 1024, 2);
        
        // Archivos donde analizar SQL (pantalla principal + model + ajax si existen)
        $archivosSql = [$fullPath];
        if (file_exists($modelPath)) {
            $archivosSql[] = $modelPath;
        }
        if (file_exists($ajaxPath)) {
            $archivosSql[] = $ajaxPath;
        }
        
        // 2. Análisis de Queries y N+1 en bucles usando token_get_all
        $cantConsultas = 0;
        $consultasEnBucle = 0;
        $queriesDetectadas = [];
        
        foreach ($archivosSql as $f) {
            $resTokens = $this->analizarTokensSql(file_get_contents($f));
            $cantConsultas += $resTokens['consultas'];
            $consultasEnBucle += $resTokens['consultas_en_bucle'];
            $queriesDetectadas = array_merge($queriesDetectadas, $resTokens['strings_sql']);
        }
        
        // 3. Patrones SQL (SELECT *, sin LIMIT, JOINs)
        $usaSelectStar = 0;
        $sinLimit = 0;
        $cantJoins = 0;
        
        foreach ($queriesDetectadas as $q) {
            if (preg_match('/SELECT\s+(\*|[a-zA-Z0-9_]+\.\*)/i', $q)) {
                $usaSelectStar = 1;
            }
            if (preg_match('/SELECT\b/i', $q) && !preg_match('/\bLIMIT\b/i', $q)) {
                // Verificar si no es un COUNT o un SELECT simple de configuración
                if (!preg_match('/COUNT\s*\(/i', $q)) {
                    $sinLimit = 1;
                }
            }
            $cantJoins += preg_match_all('/\b(LEFT|RIGHT|INNER|CROSS)?\s*JOIN\b/i', $q);
        }
        
        // 4. Carga de JS y CSS (propios y de terceros)
        $assets = $this->analizarAssets($contenido, file_exists($jsPath) ? file_get_contents($jsPath) : '');
        
        // 5. Renderizado de tablas sin paginación server-side
        $renderizaSinPaginacion = 0;
        $tieneDataTable = (stripos($contenido, 'DataTable(') !== false || (file_exists($jsPath) && stripos(file_get_contents($jsPath), 'DataTable(') !== false));
        $esServerSide = (stripos($contenido, 'serverSide') !== false || (file_exists($jsPath) && stripos(file_get_contents($jsPath), 'serverSide') !== false));
        if ($tieneDataTable && !$esServerSide) {
            $renderizaSinPaginacion = 1; // DataTable client-side: trae todo el padrón a memoria del navegador
        }
        
        // Observaciones destacadas
        $observaciones = [];
        if ($consultasEnBucle > 0) {
            $observaciones[] = "CRÍTICO: $consultasEnBucle consulta(s) ejecutada(s) dentro de bucles (problema N+1).";
        }
        if ($renderizaSinPaginacion) {
            $observaciones[] = "DataTable configurado en modo Client-Side (sin serverSide: true), descarga registros completos.";
        }
        if ($usaSelectStar) {
            $observaciones[] = "Uso de SELECT * sobre tablas de datos.";
        }
        if ($sinLimit && $cantConsultas > 0) {
            $observaciones[] = "Consultas SELECT sin cláusula LIMIT.";
        }
        if ($cantJoins >= 4) {
            $observaciones[] = "Consultas con JOINs múltiples ($cantJoins joins acumulados).";
        }
        
        return [
            'kb_archivo' => $kbArchivo,
            'lineas' => $lineas,
            'kb_includes' => $kbIncludes,
            'cant_consultas' => $cantConsultas,
            'consultas_en_bucle' => $consultasEnBucle,
            'usa_select_star' => $usaSelectStar,
            'sin_limit' => $sinLimit,
            'cant_joins' => $cantJoins,
            'carga_js_css_kb' => $assets['kb_total'],
            'cant_js' => $assets['cant_js'],
            'cant_css' => $assets['cant_css'],
            'renderiza_sin_paginacion' => $renderizaSinPaginacion,
            'observaciones' => implode(' | ', $observaciones)
        ];
    }
    
    private function extraerIncludes(string $filePath, string $content): array {
        $includes = [];
        $dir = dirname($filePath);
        
        if (preg_match_all('/(?:include|require|include_once|require_once)\s*(?:\(?\s*[\'"]([^\'"]+)[\'"]|\(?\s*ROOT_PATH\s*\.\s*[\'"]([^\'"]+)[\'"]|\(?\s*__DIR__\s*\.\s*[\'"]([^\'"]+)[\'"])/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $target = $m[1] ?: $m[2] ?: $m[3] ?: '';
                if (!$target) continue;
                
                if (isset($m[2]) && $m[2]) {
                    $resPath = $this->rootPath . '/' . ltrim($target, '/');
                } elseif (isset($m[3]) && $m[3]) {
                    $resPath = $dir . '/' . ltrim($target, '/');
                } else {
                    $resPath = $dir . '/' . ltrim($target, '/');
                }
                
                $real = realpath($resPath);
                if ($real && !in_array($real, $includes)) {
                    $includes[] = str_replace('\\', '/', $real);
                }
            }
        }
        return $includes;
    }
    
    private function analizarTokensSql(string $code): array {
        $tokens = @token_get_all($code);
        $loopDepth = 0;
        $cantConsultas = 0;
        $consultasEnBucle = 0;
        $stringsSql = [];
        
        $braceDepth = 0;
        $loopBraceDepths = [];
        
        for ($i = 0; $i < count($tokens); $i++) {
            $t = $tokens[$i];
            
            if (is_array($t)) {
                $id = $t[0];
                $text = $t[1];
                
                // Control de bucles
                if ($id === T_WHILE || $id === T_FOR || $id === T_FOREACH || $id === T_DO) {
                    $loopDepth++;
                    $loopBraceDepths[] = $braceDepth;
                }
                
                // Detección de queries
                if ($id === T_STRING) {
                    $fnLower = strtolower($text);
                    if (in_array($fnLower, ['mysqli_query', 'mysqli_prepare', 'mysqli_stmt_execute'])) {
                        $cantConsultas++;
                        if ($loopDepth > 0) {
                            $consultasEnBucle++;
                        }
                    }
                }
                
                // Extracción de strings que contengan SQL
                if ($id === T_CONSTANT_ENCAPSED_STRING || $id === T_ENCAPSED_AND_WHITESPACE) {
                    if (preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $text)) {
                        $clean = trim($text, "\"'");
                        $stringsSql[] = $clean;
                    }
                }
            } else {
                if ($t === '{') {
                    $braceDepth++;
                } elseif ($t === '}') {
                    $braceDepth--;
                    if (!empty($loopBraceDepths) && end($loopBraceDepths) >= $braceDepth) {
                        array_pop($loopBraceDepths);
                        $loopDepth = max(0, $loopDepth - 1);
                    }
                }
            }
        }
        
        return [
            'consultas' => $cantConsultas,
            'consultas_en_bucle' => $consultasEnBucle,
            'strings_sql' => $stringsSql
        ];
    }
    
    private function analizarAssets(string $htmlContent, string $jsContent): array {
        $totalKb = 0;
        $cantJs = 0;
        $cantCss = 0;
        
        // CSS
        if (preg_match_all('/<link[^>]+href=[\'"]([^\'"]+\.css[^\'"]*)[\'"]/i', $htmlContent, $matches)) {
            $cantCss = count($matches[1]);
            foreach ($matches[1] as $href) {
                $totalKb += $this->resolverPesoAsset($href);
            }
        }
        
        // JS
        if (preg_match_all('/<script[^>]+src=[\'"]([^\'"]+\.js[^\'"]*)[\'"]/i', $htmlContent, $matches)) {
            $cantJs = count($matches[1]);
            foreach ($matches[1] as $src) {
                $totalKb += $this->resolverPesoAsset($src);
            }
        }
        
        // Si no detectó tags directos (porque están en header1.php), usar el bundle base de AdminLTE
        if ($cantCss === 0 && $cantJs === 0) {
            // AdminLTE + Bootstrap + DataTables base pesa típicamente ~850 KB
            $totalKb = 850.00;
            $cantCss = 8;
            $cantJs = 9;
        }
        
        return [
            'kb_total' => round($totalKb, 2),
            'cant_js' => $cantJs,
            'cant_css' => $cantCss
        ];
    }
    
    private function resolverPesoAsset(string $url): float {
        // Limpiar query strings
        $clean = parse_url($url, PHP_URL_PATH);
        if (!$clean) return 15.0; // peso default
        
        $localPath = $this->rootPath . '/' . ltrim($clean, '/');
        if (file_exists($localPath)) {
            return round(filesize($localPath) / 1024, 2);
        }
        return 25.0; // peso estimado para asset externo o compilado
    }
}
