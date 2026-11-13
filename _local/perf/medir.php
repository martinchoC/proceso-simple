<?php
// perf/medir.php
// Script principal CLI de medición de performance reutilizable

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/analizador_estatico.php';

$options = getopt('', [
    'modulo::',
    'pagina::',
    'repeticiones::',
    'corrida_id::'
]);

$filtroModulo = $options['modulo'] ?? null;
$filtroPagina = $options['pagina'] ?? null;
$repeticiones = isset($options['repeticiones']) ? max(1, (int)$options['repeticiones']) : 5;
$corridaId = isset($options['corrida_id']) ? (int)$options['corrida_id'] : null;

// Obtener versión de código (git commit)
$versionCodigo = 'local-dev';
if (file_exists(__DIR__ . '/../../.git')) {
    $head = @file_get_contents(__DIR__ . '/../../.git/HEAD');
    if ($head) {
        if (preg_match('/ref:\s*(.+)/', $head, $m)) {
            $refPath = __DIR__ . '/../../.git/' . trim($m[1]);
            if (file_exists($refPath)) {
                $versionCodigo = substr(trim(file_get_contents($refPath)), 0, 7);
            }
        } else {
            $versionCodigo = substr(trim($head), 0, 7);
        }
    }
}

// Crear o reutilizar corrida
if (!$corridaId) {
    $obs = "Medición de performance: " . ($filtroModulo ? "Módulo $filtroModulo" : "Todos los módulos") . ($filtroPagina ? " - Página $filtroPagina" : "");
    $stmtC = mysqli_prepare($conn, "INSERT INTO perf_corridas (fecha, entorno, version_codigo, observaciones) VALUES (NOW(), 'desarrollo', ?, ?)");
    mysqli_stmt_bind_param($stmtC, "ss", $versionCodigo, $obs);
    mysqli_stmt_execute($stmtC);
    $corridaId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmtC);
}

echo "========================================================\n";
echo "INICIANDO MEDICION - CORRIDA ID #$corridaId\n";
echo "Fecha: " . date('Y-m-d H:i:s') . " | Repeticiones: $repeticiones | Git: $versionCodigo\n";
echo "========================================================\n\n";

$analizador = new AnalizadorEstatico(__DIR__ . '/../..');

// Obtener módulos a procesar
$sqlMod = "SELECT DISTINCT modulo FROM perf_paginas";
if ($filtroModulo) {
    $sqlMod .= " WHERE modulo = '" . mysqli_real_escape_string($conn, $filtroModulo) . "'";
}
$sqlMod .= " ORDER BY modulo";

$resMod = mysqli_query($conn, $sqlMod);
$modulosAProcesar = [];
while ($r = mysqli_fetch_assoc($resMod)) {
    $modulosAProcesar[] = $r['modulo'];
}

foreach ($modulosAProcesar as $modulo) {
    echo ">>> PROCESANDO MODULO: [ $modulo ] <<<\n";
    
    $sqlPags = "SELECT pagina_id, modulo, archivo, ruta, ejecutable_dinamico, motivo_no_ejecutada 
                FROM perf_paginas 
                WHERE modulo = '" . mysqli_real_escape_string($conn, $modulo) . "'";
    if ($filtroPagina) {
        $sqlPags .= " AND archivo = '" . mysqli_real_escape_string($conn, $filtroPagina) . "'";
    }
    $sqlPags .= " ORDER BY archivo";
    
    $resPags = mysqli_query($conn, $sqlPags);
    $paginasModulo = [];
    while ($p = mysqli_fetch_assoc($resPags)) {
        $paginasModulo[] = $p;
    }
    
    $cantPaginas = count($paginasModulo);
    $scoresModulo = [];
    $msTotalModulo = 0;
    $kbHtmlTotalModulo = 0;
    $consultasTotalModulo = 0;
    
    foreach ($paginasModulo as $idx => $pag) {
        $pagId = (int)$pag['pagina_id'];
        $archivo = $pag['archivo'];
        $ruta = $pag['ruta'];
        $ejecutable = (int)$pag['ejecutable_dinamico'];
        
        echo sprintf(" [%2d/%2d] %-35s ", $idx + 1, $cantPaginas, $archivo);
        
        // 1. ANALISIS ESTATICO
        $estatico = $analizador->analizar($ruta);
        
        $stmtEst = mysqli_prepare($conn, "INSERT INTO perf_estatico 
            (corrida_id, pagina_id, kb_archivo, lineas, kb_includes, cant_consultas, consultas_en_bucle, usa_select_star, sin_limit, cant_joins, carga_js_css_kb, observaciones) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmtEst, "iididiiiiids", 
            $corridaId, 
            $pagId, 
            $estatico['kb_archivo'], 
            $estatico['lineas'], 
            $estatico['kb_includes'], 
            $estatico['cant_consultas'], 
            $estatico['consultas_en_bucle'], 
            $estatico['usa_select_star'], 
            $estatico['sin_limit'], 
            $estatico['cant_joins'], 
            $estatico['carga_js_css_kb'], 
            $estatico['observaciones']
        );
        mysqli_stmt_execute($stmtEst);
        mysqli_stmt_close($stmtEst);
        
        // 2. MEDICION DINAMICA
        $msPromedio = 0;
        $msMax = 0;
        $kbHtml = 0;
        $kbHtmlGzip = 0;
        $memoriaPicoMb = 0;
        $cantConsultasDinamicas = 0;
        $msMysql = 0;
        $filasMax = 0;
        $consultasCapturadas = [];
        
        if ($ejecutable === 1) {
            $tiempos = [];
            $mems = [];
            $htmls = [];
            $gzips = [];
            $queriesRun = [];
            $mysqlTimes = [];
            
            $empresaParam = ($modulo === 'gestion_normal') ? 6 : 2;
            
            for ($rep = 0; $rep < $repeticiones; $rep++) {
                $cmd = sprintf(
                    'C:\\xampp\\php\\php.exe -d auto_prepend_file=perf/prepend_profiler.php perf/run_page.php --target=%s --empresa_id=%d',
                    escapeshellarg($ruta),
                    $empresaParam
                );
                
                $descriptors = [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w']
                ];
                $proc = proc_open($cmd, $descriptors, $pipes, __DIR__ . '/../..');
                $out = stream_get_contents($pipes[1]);
                $err = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);
                
                if (preg_match('/___PERF_METRICS_START___(.*?)___PERF_METRICS_END___/s', $out, $match)) {
                    $datos = json_decode($match[1], true);
                    if ($datos) {
                        $tiempos[] = $datos['ms_total'];
                        $mems[] = $datos['memoria_pico_mb'];
                        $htmls[] = $datos['kb_html'];
                        $gzips[] = $datos['kb_html_gzip'];
                        $queriesRun[] = $datos['cant_consultas'];
                        $mysqlTimes[] = $datos['ms_mysql'];
                        if (!empty($datos['consultas'])) {
                            $consultasCapturadas = $datos['consultas'];
                        }
                    }
                }
            }
            
            if (!empty($tiempos)) {
                $msPromedio = round(array_sum($tiempos) / count($tiempos), 2);
                $msMax = round(max($tiempos), 2);
                $kbHtml = round(array_sum($htmls) / count($htmls), 2);
                $kbHtmlGzip = round(array_sum($gzips) / count($gzips), 2);
                $memoriaPicoMb = round(max($mems), 2);
                $cantConsultasDinamicas = (int)round(array_sum($queriesRun) / count($queriesRun));
                $msMysql = round(array_sum($mysqlTimes) / count($mysqlTimes), 2);
            }
            
            // Guardar dinámico
            $stmtDin = mysqli_prepare($conn, "INSERT INTO perf_dinamico 
                (corrida_id, pagina_id, escenario, ms_promedio, ms_max, kb_html, kb_html_gzip, memoria_pico_mb, cant_consultas, ms_mysql, filas_max) 
                VALUES (?, ?, 'default', ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmtDin, "iidddddidi", 
                $corridaId, 
                $pagId, 
                $msPromedio, 
                $msMax, 
                $kbHtml, 
                $kbHtmlGzip, 
                $memoriaPicoMb, 
                $cantConsultasDinamicas, 
                $msMysql, 
                $filasMax
            );
            mysqli_stmt_execute($stmtDin);
            mysqli_stmt_close($stmtDin);
            
            // Procesar consultas capturadas y EXPLAIN
            if (!empty($consultasCapturadas)) {
                $normalizadas = [];
                foreach ($consultasCapturadas as $cq) {
                    $sqlRaw = trim($cq['sql']);
                    if (stripos($sqlRaw, 'SHOW PROFILES') !== false || stripos($sqlRaw, 'SET profiling') !== false) continue;
                    
                    // Normalizar SQL (reemplazar números y strings literales por ?)
                    $norm = preg_replace('/\b\d+\b/', '?', $sqlRaw);
                    $norm = preg_replace('/\'[^\']*\'/', '?', $norm);
                    $norm = trim(preg_replace('/\s+/', ' ', $norm));
                    
                    if (!isset($normalizadas[$norm])) {
                        $normalizadas[$norm] = [
                            'sql_original' => $sqlRaw,
                            'veces' => 1,
                            'ms_total' => $cq['duration_ms']
                        ];
                    } else {
                        $normalizadas[$norm]['veces']++;
                        $normalizadas[$norm]['ms_total'] += $cq['duration_ms'];
                    }
                }
                
                foreach ($normalizadas as $sqlNorm => $infoNorm) {
                    $usaIndice = 'NO';
                    $indiceSugerido = null;
                    
                    // Si es un SELECT y no está truncado, hacer EXPLAIN
                    if (preg_match('/^SELECT\b/i', $infoNorm['sql_original'])) {
                        try {
                            $resExp = @mysqli_query($conn, "EXPLAIN " . $infoNorm['sql_original']);
                            if ($resExp) {
                                $fullScan = false;
                                $keyUsed = null;
                                while ($re = mysqli_fetch_assoc($resExp)) {
                                    if (isset($re['type']) && $re['type'] === 'ALL') {
                                        $fullScan = true;
                                    }
                                    if (!empty($re['key'])) {
                                        $keyUsed = $re['key'];
                                    }
                                }
                                if ($fullScan) {
                                    $usaIndice = 'NO (Full Table Scan)';
                                    if (preg_match('/FROM\s+([a-zA-Z0-9_]+)\b/i', $infoNorm['sql_original'], $mTab)) {
                                        $tabla = $mTab[1];
                                        $indiceSugerido = "Crear índice en $tabla para columnas de filtro/JOIN";
                                    }
                                } elseif ($keyUsed) {
                                    $usaIndice = "SI ($keyUsed)";
                                }
                            }
                        } catch (\Throwable $exExplain) {
                            // Si la query estaba truncada por SHOW PROFILES u otro motivo, omitir EXPLAIN
                        }
                    }
                    
                    $stmtCq = mysqli_prepare($conn, "INSERT INTO perf_consultas 
                        (corrida_id, pagina_id, sql_normalizado, veces_ejecutada, ms_total, filas, usa_indice, indice_sugerido) 
                        VALUES (?, ?, ?, ?, ?, 0, ?, ?)");
                    $msTotRound = round($infoNorm['ms_total'], 2);
                    mysqli_stmt_bind_param($stmtCq, "iisidss", 
                        $corridaId, 
                        $pagId, 
                        $sqlNorm, 
                        $infoNorm['veces'], 
                        $msTotRound, 
                        $usaIndice, 
                        $indiceSugerido
                    );
                    mysqli_stmt_execute($stmtCq);
                    mysqli_stmt_close($stmtCq);
                }
            }
        }
        
        // 3. CALCULO DE PUNTAJE DE CARGA (0 - 100)
        // Pesos:
        // - Tiempo respuesta: 35%
        // - Consultas y N+1: 25%
        // - Tiempo MySQL: 15%
        // - Bytes HTML: 15%
        // - Memoria: 10%
        
        // Normalización individual (0 a 100):
        $scoreMs = min(100, max(0, ($msPromedio - 20) / 10)); // 20ms = 0, 1020ms = 100
        $scoreQueries = min(100, ($estatico['consultas_en_bucle'] * 35) + ($estatico['cant_consultas'] * 3) + ($cantConsultasDinamicas * 2));
        $scoreMysql = min(100, max(0, $msMysql * 2)); // 50ms mysql = 100
        $scoreHtml = min(100, max(0, ($kbHtml / 300) * 100)); // 300kb = 100
        $scoreMem = min(100, max(0, ($memoriaPicoMb / 16) * 100)); // 16mb = 100
        
        $scorePagina = round(
            ($scoreMs * 0.35) + 
            ($scoreQueries * 0.25) + 
            ($scoreMysql * 0.15) + 
            ($scoreHtml * 0.15) + 
            ($scoreMem * 0.10), 
            2
        );
        
        // Clasificación
        $clasif = "LIVIANA";
        if ($scorePagina > 60) {
            $clasif = "PESADA";
        } elseif ($scorePagina > 30) {
            $clasif = "MEDIA";
        }
        
        $scoresModulo[$archivo] = [
            'score' => $scorePagina,
            'clasif' => $clasif,
            'ms' => $msPromedio,
            'queries' => $estatico['cant_consultas'] + $cantConsultasDinamicas,
            'n1' => $estatico['consultas_en_bucle'],
            'html' => $kbHtml ?: $estatico['kb_archivo'],
            'obs' => $estatico['observaciones'] ?: ($ejecutable ? 'OK' : 'No ejecutada')
        ];
        
        $msTotalModulo += $msPromedio;
        $kbHtmlTotalModulo += $kbHtml;
        $consultasTotalModulo += ($estatico['cant_consultas'] + $cantConsultasDinamicas);
        
        echo sprintf("-> Score: %5.1f [%-7s] | %6.1f ms | %2d queries | N+1: %d\n", 
            $scorePagina, $clasif, $msPromedio, $cantConsultasDinamicas, $estatico['consultas_en_bucle']);
    }
    
    // Resumen de módulo
    $msPromedioModulo = $cantPaginas > 0 ? round($msTotalModulo / $cantPaginas, 2) : 0;
    $scoreModuloPromedio = 0;
    if (!empty($scoresModulo)) {
        $sumaScores = array_sum(array_column($scoresModulo, 'score'));
        $scoreModuloPromedio = round($sumaScores / count($scoresModulo), 2);
    }
    
    $stmtResMod = mysqli_prepare($conn, "REPLACE INTO perf_resumen_modulo 
        (corrida_id, modulo, cant_paginas, ms_promedio, kb_html_total, consultas_total, score_modulo) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmtResMod, "isiddid", 
        $corridaId, 
        $modulo, 
        $cantPaginas, 
        $msPromedioModulo, 
        $kbHtmlTotalModulo, 
        $consultasTotalModulo, 
        $scoreModuloPromedio
    );
    mysqli_stmt_execute($stmtResMod);
    mysqli_stmt_close($stmtResMod);
    
    // Ordenar páginas por peso para el Top 3
    uasort($scoresModulo, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    
    $top3 = array_slice($scoresModulo, 0, 3, true);
    
    echo "\n--------------------------------------------------------\n";
    echo "RESUMEN CORTO DEL MODULO: $modulo\n";
    echo "Total Páginas: $cantPaginas | Score Promedio Módulo: $scoreModuloPromedio\n";
    echo "Top 3 Páginas más pesadas:\n";
    $puesto = 1;
    foreach ($top3 as $nomArch => $infoP) {
        echo sprintf("  %d. %-32s Score: %5.1f [%s] (%.1f ms, %d queries, N+1: %d) -> %s\n", 
            $puesto++, $nomArch, $infoP['score'], $infoP['clasif'], $infoP['ms'], $infoP['queries'], $infoP['n1'], $infoP['obs']);
    }
    echo "--------------------------------------------------------\n\n";
}

echo "Medición finalizada con éxito. Corrida ID #$corridaId registrada en la base de datos.\n";
