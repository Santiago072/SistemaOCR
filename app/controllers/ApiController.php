<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Ficha;
use App\Models\OcrJob;

/**
 * Controlador de API para verificación asíncrona de estados
 * PASO 5: Endpoint SSE para monitoreo en tiempo real
 */
class ApiController extends Controller
{
    private Ficha $fichaModel;
    private OcrJob $ocrJobModel;

    public function __construct(Ficha $fichaModel, OcrJob $ocrJobModel)
    {
        $this->fichaModel = $fichaModel;
        $this->ocrJobModel = $ocrJobModel;
    }

    /**
     * Endpoint tradicional para verificar estado
     */
    public function estadoProceso(): void
    {
        $fichaId = (int)($_GET['ficha'] ?? 0);
        $ficha = $this->fichaModel->findById($fichaId);

        if (!$ficha) {
            $this->json(['error' => 'Ficha no encontrada'], 404);
        }

        $this->json([
            'id' => $ficha['id'],
            'estado' => $ficha['estado'],
            'total_inscritos' => $ficha['total_inscritos']
        ]);
    }

    /**
     * PASO 5: Endpoint SSE para stream de progreso en tiempo real
     * Uso: var sse = new EventSource('/api/progress/ocr/{jobId}');
     *      sse.onmessage = (event) => { console.log(JSON.parse(event.data)); };
     */
    public function progressStream(): void
    {
        // Obtener jobId del URL (ej: /api/progress/ocr/abc123def456)
        $path = $_GET['ruta'] ?? '';
        preg_match('#/ocr/([a-zA-Z0-9_\.]+)$#', $path, $m);
        $jobId = $m[1] ?? null;

        if (!$jobId) {
            http_response_code(400);
            echo "error: jobId no proporcionado\n\n";
            exit;
        }

        // Headers SSE
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');
        header('X-Accel-Buffering: no');  // Nginx: sin buffering
        
        // Desactivar output buffering
        if (ob_get_level()) ob_end_clean();
        
        // Enviar progreso inicial
        $progress = $this->ocrJobModel->getCurrentProgress($jobId);
        echo "data: " . json_encode($progress) . "\n\n";
        flush();

        // Stream continuo hasta completarse
        $maxIterations = 300;  // ~5 minutos máximo (300 * 1s)
        $iterations = 0;

        while ($iterations++ < $maxIterations) {
            $progress = $this->ocrJobModel->getCurrentProgress($jobId);

            // Enviar evento
            echo "data: " . json_encode($progress) . "\n\n";
            flush();

            // Si completado o error, terminar stream
            if (in_array($progress['status'], ['COMPLETED', 'ERROR'])) {
                break;
            }

            // Pausa antes de siguiente actualización
            sleep(1);
        }

        // Final del stream
        echo ":stream-end\n\n";
        flush();
        exit;
    }

    /**
     * PASO 5: Endpoint para obtener eventos recientes de un job (alternativa a SSE)
     * Uso: fetch('/api/job/{jobId}/events')
     */
    public function jobEvents(): void
    {
        $path = $_GET['ruta'] ?? '';
        preg_match('#/job/([a-zA-Z0-9_\.]+)/events#', $path, $m);
        $jobId = $m[1] ?? null;

        if (!$jobId) {
            $this->json(['error' => 'jobId no proporcionado'], 400);
        }

        $events = $this->ocrJobModel->getRecentEvents($jobId, 50);
        $progress = $this->ocrJobModel->getCurrentProgress($jobId);

        $this->json([
            'job' => $progress,
            'events' => $events
        ]);
    }

    /**
     * Endpoint proxy para consultar estado del trabajo en microservicio OCR
     */
    public function estadoTrabajo(): void
    {
        $trabajo = $_GET['trabajo'] ?? '';
        if (!$trabajo) {
            $this->json(['error' => 'Falta parámetro trabajo'], 400);
        }

        $ch = curl_init('http://127.0.0.1:5005/api/' . urlencode($trabajo) . '/estado');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 4,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);

        header('Content-Type: application/json; charset=utf-8');
        echo $res ?: json_encode(['etapa' => 'desconocido']);
        exit;
    }

    /**
     * Endpoint proxy para consultar avance parcial (cédulas ya leídas) en microservicio OCR
     */
    public function parcialTrabajo(): void
    {
        $trabajo = $_GET['trabajo'] ?? '';
        if (!$trabajo) {
            $this->json(['error' => 'Falta parámetro trabajo'], 400);
        }

        $ch = curl_init('http://127.0.0.1:5005/api/' . urlencode($trabajo) . '/parcial');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 4,
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 404 || !$res) {
            http_response_code(404);
            $this->json(['error' => 'Sin datos parciales todavía']);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo $res;
        exit;
    }

    /**
     * Endpoint proxy para consultar datos finales completos (del microservicio o de la base de datos MySQL)
     */
    public function datosTrabajo(): void
    {
        $trabajo = $_GET['trabajo'] ?? '';
        $fichaId = (int)($_GET['ficha_id'] ?? 0);

        if ($trabajo) {
            $ch = curl_init('http://127.0.0.1:5005/api/' . urlencode($trabajo) . '/datos');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            $res = curl_exec($ch);
            curl_close($ch);

            header('Content-Type: application/json; charset=utf-8');
            echo $res ?: json_encode(['error' => 'No encontrado']);
            exit;
        }

        if ($fichaId > 0) {
            $db = \App\Core\Database::getConnection();
            $stmt = $db->prepare("
                SELECT 
                    c.estado_cruce,
                    c.observaciones,
                    d.tipo_documento,
                    d.numero_documento,
                    d.primer_nombre,
                    d.segundo_nombre,
                    d.primer_apellido,
                    d.segundo_apellido,
                    d.nombre_completo_ocr,
                    d.fecha_nacimiento,
                    d.rh,
                    d.numero_pagina,
                    d.ruta_imagen_recorte,
                    a.nombre_completo as listado_nombre,
                    a.tipo_documento as listado_tipo_doc,
                    a.numero_documento as listado_num_doc
                FROM cruce_conciliacion c
                LEFT JOIN documentos_pdf_ocr d ON c.documento_pdf_id = d.id
                LEFT JOIN aspirantes_excel a ON c.aspirante_excel_id = a.id
                WHERE c.ficha_id = :fid
                ORDER BY d.numero_pagina ASC
            ");
            $stmt->execute(['fid' => $fichaId]);
            $rows = $stmt->fetchAll();

            $estadoMap = [
                'CONCILIADO' => 'ok',
                'DIFERENCIA_NOMBRE' => 'revisar',
                'SOBRANTE_PDF' => 'sin_listado',
            ];

            // Si aún no hay cruce en BD pero hay aspirantes en aspirantes_excel, cargarlos como estado preliminar
            if (empty($rows)) {
                $stmtAsp = $db->prepare("SELECT * FROM aspirantes_excel WHERE ficha_id = :fid ORDER BY id ASC");
                $stmtAsp->execute(['fid' => $fichaId]);
                $aspirantes = $stmtAsp->fetchAll();
                foreach ($aspirantes as $asp) {
                    $faltantes[] = [
                        'documento' => $asp['numero_documento'],
                        'tipo' => $asp['tipo_documento'] ?: 'CC',
                        'nombre_completo' => $asp['nombre_completo'],
                        'nombres' => $asp['primer_nombre'] ?? '',
                        'apellidos' => $asp['primer_apellido'] ?? '',
                    ];
                }
            } else {
                foreach ($rows as $r) {
                    if (empty($r['numero_documento']) && empty($r['listado_num_doc'])) continue;
                    
                    if ($r['estado_cruce'] === 'FALTANTE_PDF') {
                        $faltantes[] = [
                            'documento' => $r['listado_num_doc'] ?: $r['numero_documento'],
                            'tipo' => $r['listado_tipo_doc'] ?: $r['tipo_documento'] ?: 'CC',
                            'nombre_completo' => $r['listado_nombre'] ?: trim(($r['primer_nombre'] ?? '') . ' ' . ($r['primer_apellido'] ?? '')),
                            'nombres' => $r['primer_nombre'] ?? '',
                            'apellidos' => $r['primer_apellido'] ?? '',
                        ];
                        continue;
                    }

                    $nomLimpio = trim($r['nombre_completo_ocr'] ?: (($r['primer_nombre'] ?? '') . ' ' . ($r['primer_apellido'] ?? '')));
                    $personas[] = [
                        'id' => count($personas),
                        'documento' => $r['numero_documento'] ?: $r['listado_num_doc'],
                        'ocr_tipo_leido' => $r['tipo_documento'] ?: 'CC',
                        'ocr_doc_leido' => $r['numero_documento'] ?: '',
                        'ocr_nombre_leido' => $nomLimpio,
                        'estado' => $estadoMap[$r['estado_cruce']] ?? 'ok',
                        'novedad' => $r['observaciones'] ?? '',
                        'valores' => [
                            'tipo_documento' => $r['tipo_documento'] ?: $r['listado_tipo_doc'] ?: 'CC',
                            'documento' => $r['numero_documento'] ?: $r['listado_num_doc'],
                            'apellidos' => trim(($r['primer_apellido'] ?? '') . ' ' . ($r['segundo_apellido'] ?? '')),
                            'nombres' => trim(($r['primer_nombre'] ?? '') . ' ' . ($r['segundo_nombre'] ?? '')),
                            'nacimiento' => $r['fecha_nacimiento'] ?? '',
                            'rh' => $r['rh'] ?? '',
                        ],
                        'paginas' => [
                            [
                                'pagina' => (int)($r['numero_pagina'] ?: (count($personas) + 1)),
                                'imagen' => $r['ruta_imagen_recorte'] ?: '',
                                'ancho' => 2200,
                                'alto' => 2800,
                                'campos' => []
                            ]
                        ],
                        'listado' => [
                            'nombre_completo' => $r['listado_nombre'] ?? '',
                            'documento' => $r['listado_num_doc'] ?? '',
                            'tipo_documento' => $r['listado_tipo_doc'] ?? ''
                        ]
                    ];
                }
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['personas' => $personas, 'faltantes' => $faltantes]);
            exit;
        }

        $this->json(['error' => 'Falta parámetro trabajo o ficha_id'], 400);
    }
}
