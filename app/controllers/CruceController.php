<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Ficha;
use App\Models\Cruce;
use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * Controlador de Informes de Cruce, Validación Manual e Importación Final
 */
class CruceController extends Controller
{
    private Ficha $fichaModel;
    private Cruce $cruceModel;
    private PDO $db;

    public function __construct(Ficha $fichaModel, Cruce $cruceModel)
    {
        $this->fichaModel = $fichaModel;
        $this->cruceModel = $cruceModel;
        $this->db = Database::getConnection();
    }

    /**
     * Muestra el dashboard del informe de cruce de una ficha
     */
    public function informe(): void
    {
        $fichaId = isset($_GET['ficha']) ? (int)$_GET['ficha'] : 0;
        $ficha = $this->fichaModel->findById($fichaId);

        if (!$ficha) {
            $this->redirect('home/index');
            return;
        }

        $trabajoId = $_GET['trabajo'] ?? '';
        $informe = $this->cruceModel->getInformeCompleto($fichaId);
        $estadisticas = $this->cruceModel->getEstadisticas($fichaId);

        // Formatear datos para inyección directa en Javascript
        $datosIniciales = null;
        if (!empty($informe)) {
            $estadoMap = [
                'CONCILIADO' => 'ok',
                'DIFERENCIA_NOMBRE' => 'revisar',
                'SOBRANTE_PDF' => 'sin_listado',
            ];
            $personas = [];
            $faltantes = [];
            foreach ($informe as $r) {
                if (empty($r['ocr_num_doc']) && empty($r['excel_num_doc'])) continue;
                if ($r['estado_cruce'] === 'FALTANTE_PDF') {
                    $faltantes[] = [
                        'documento' => $r['excel_num_doc'] ?: $r['ocr_num_doc'],
                        'tipo' => $r['excel_tipo_doc'] ?: $r['ocr_tipo_doc'] ?: 'CC',
                        'nombre_completo' => $r['excel_nombre'] ?: $r['ocr_nombre'],
                        'nombres' => $r['excel_nombre'] ?? '',
                        'apellidos' => '',
                    ];
                    continue;
                }

                $nombres = trim(($r['ocr_primer_nombre'] ?? '') . ' ' . ($r['ocr_segundo_nombre'] ?? ''));
                $apellidos = trim(($r['ocr_primer_apellido'] ?? '') . ' ' . ($r['ocr_segundo_apellido'] ?? ''));
                if (!$nombres && !$apellidos) {
                    $nomLimpio = trim($r['ocr_nombre'] ?: $r['excel_nombre'] ?: '');
                    $nombres = $nomLimpio;
                }

                $personas[] = [
                    'id' => count($personas),
                    'documento' => $r['ocr_num_doc'] ?: $r['excel_num_doc'],
                    'ocr_tipo_leido' => $r['ocr_tipo_doc'] ?: 'CC',
                    'ocr_doc_leido' => $r['ocr_num_doc'] ?: '',
                    'ocr_nombre_leido' => trim("{$nombres} {$apellidos}"),
                    'estado' => $estadoMap[$r['estado_cruce']] ?? 'ok',
                    'novedad' => $r['observaciones'] ?? '',
                    'valores' => [
                        'tipo_documento' => $r['ocr_tipo_doc'] ?: $r['excel_tipo_doc'] ?: 'CC',
                        'documento' => $r['ocr_num_doc'] ?: $r['excel_num_doc'],
                        'apellidos' => $apellidos,
                        'nombres' => $nombres,
                        'nacimiento' => $r['ocr_nacimiento'] ?? '',
                        'rh' => $r['ocr_rh'] ?? '',
                    ],
                    'paginas' => [
                        [
                            'pagina' => (int)($r['pdf_pagina'] ?: (count($personas) + 1)),
                            'imagen' => $r['ruta_imagen_recorte'] ?: '',
                        ]
                    ],
                    'listado' => [
                        'nombre_completo' => $r['excel_nombre'] ?? '',
                        'documento' => $r['excel_num_doc'] ?? '',
                        'tipo_documento' => $r['excel_tipo_doc'] ?? ''
                    ]
                ];
            }
            $datosIniciales = ['personas' => $personas, 'faltantes' => $faltantes];
        }

        $this->view('cruce/informe', [
            'titulo' => "Informe de Cruce - Ficha " . htmlspecialchars($ficha['codigo_ficha']),
            'ficha' => $ficha,
            'informe' => $informe,
            'estadisticas' => $estadisticas,
            'datosIniciales' => $datosIniciales,
            'extraCss' => ['cruce.css'],
            'extraJs' => ['cruce-dashboard.js']
        ]);
    }

    /**
     * Valida o ajusta manualmente un registro con discrepancia
     */
    public function validarManual(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método no permitido'], 405);
        }

        $cruceId = (int)($_POST['cruce_id'] ?? 0);
        $accion = $_POST['accion'] ?? 'APROBAR'; // APROBAR o DESCARTAR

        if (!$cruceId) {
            $this->json(['error' => 'ID de cruce no proporcionado'], 400);
        }

        $nuevoEstado = ($accion === 'APROBAR') ? 'CONCILIADO' : 'ILEGIBLE';

        $stmt = $this->db->prepare("
            UPDATE cruce_conciliacion 
            SET estado_cruce = :estado, validado_manualmente = 1, fecha_validacion = NOW() 
            WHERE id = :id
        ");
        $stmt->execute(['estado' => $nuevoEstado, 'id' => $cruceId]);

        $this->json(['success' => true, 'nuevo_estado' => $nuevoEstado]);
    }

    /**
     * Importa todos los registros aprobados/conciliados a la tabla final
     */
    public function importarFinal(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método no permitido'], 405);
        }

        $fichaId = (int)($_POST['ficha_id'] ?? 0);
        if (!$fichaId) {
            $this->json(['error' => 'ID de ficha no válido'], 400);
        }

        // Obtener cruces conciliados
        $sql = "
            SELECT 
                ae.tipo_documento,
                ae.numero_documento,
                ae.nombre_completo,
                ae.estado_inscripcion,
                dpo.primer_nombre,
                dpo.segundo_nombre,
                dpo.primer_apellido,
                dpo.segundo_apellido,
                dpo.genero,
                dpo.fecha_nacimiento,
                dpo.rh,
                c.validado_manualmente
            FROM cruce_conciliacion c
            JOIN aspirantes_excel ae ON c.aspirante_excel_id = ae.id
            LEFT JOIN documentos_pdf_ocr dpo ON c.documento_pdf_id = dpo.id
            WHERE c.ficha_id = :ficha_id AND c.estado_cruce = 'CONCILIADO'
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['ficha_id' => $fichaId]);
        $conciliados = $stmt->fetchAll();

        if (empty($conciliados)) {
            $this->json(['error' => 'No hay participantes conciliados para importar.'], 400);
        }

        // Insertar en participantes_finales
        $insertSql = "
            INSERT INTO participantes_finales 
            (ficha_id, tipo_documento, numero_documento, nombres, apellidos, nombre_completo, genero, fecha_nacimiento, rh, estado_inscripcion, origen_validacion)
            VALUES (:ficha_id, :tipo_doc, :num_doc, :nombres, :apellidos, :nombre_completo, :genero, :fnac, :rh, :estado_ins, :origen)
            ON DUPLICATE KEY UPDATE 
                nombres = VALUES(nombres),
                apellidos = VALUES(apellidos),
                nombre_completo = VALUES(nombre_completo),
                genero = VALUES(genero),
                fecha_nacimiento = VALUES(fecha_nacimiento),
                rh = VALUES(rh),
                estado_inscripcion = VALUES(estado_inscripcion)
        ";

        $stmtInsert = $this->db->prepare($insertSql);

        $importados = 0;
        foreach ($conciliados as $row) {
            $nombres = trim(($row['primer_nombre'] ?? '') . ' ' . ($row['segundo_nombre'] ?? ''));
            $apellidos = trim(($row['primer_apellido'] ?? '') . ' ' . ($row['segundo_apellido'] ?? ''));
            $nombreFinal = !empty($nombres) ? "{$nombres} {$apellidos}" : $row['nombre_completo'];

            $stmtInsert->execute([
                'ficha_id'          => $fichaId,
                'tipo_doc'          => $row['tipo_documento'] ?? 'CC',
                'num_doc'           => $row['numero_documento'],
                'nombres'           => $nombres ?: $row['nombre_completo'],
                'apellidos'         => $apellidos,
                'nombre_completo'   => $nombreFinal,
                'genero'            => $row['genero'] ?? null,
                'fnac'              => !empty($row['fecha_nacimiento']) ? date('Y-m-d', strtotime(str_replace('/', '-', $row['fecha_nacimiento']))) : null,
                'rh'                => $row['rh'] ?? null,
                'estado_ins'        => $row['estado_inscripcion'] ?? 'INSCRITO',
                'origen'            => $row['validado_manualmente'] ? 'MANUAL' : 'AUTOMATICO_OCR'
            ]);
            $importados++;
        }

        // Actualizar estado de la ficha
        $this->fichaModel->updateEstado($fichaId, 'FINALIZADA');

        $this->json([
            'success' => true,
            'importados' => $importados,
            'mensaje' => "Se han importado exitosamente {$importados} participantes a la base de datos."
        ]);
    }

    /**
     * Sincroniza los resultados completos del microservicio OCR con la base de datos MySQL
     */
    public function sincronizar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método no permitido'], 405);
        }

        $fichaId = (int)($_POST['ficha_id'] ?? 0);
        $trabajoId = $_POST['trabajo_id'] ?? '';

        if (!$fichaId || !$trabajoId) {
            $this->json(['error' => 'Datos insuficientes para sincronizar'], 400);
        }

        $resultado = $this->ejecutarSincronizacionInterna($fichaId, $trabajoId, (float)($_POST['tiempo_seg'] ?? 0));

        $this->json([
            'success' => $resultado['success'] ?? false,
            'sincronizados' => $resultado['sincronizados'] ?? 0,
            'mensaje' => $resultado['mensaje'] ?? 'Procesado'
        ]);
    }

    /**
     * Lógica central de sincronización reutilizable internamente por PHP
     */
    public function ejecutarSincronizacionInterna(int $fichaId, string $trabajoId, float $segundos = 0): array
    {
        if (!$fichaId || !$trabajoId) {
            return ['success' => false, 'error' => 'Datos insuficientes'];
        }

        $ch = curl_init('http://127.0.0.1:5005/api/' . urlencode($trabajoId) . '/datos');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $datos = json_decode($response ?: '{}', true);
        if (empty($datos['personas'])) {
            return ['success' => false, 'error' => 'No hay datos listos'];
        }

        $personas = $datos['personas'] ?? [];
        $uploadDir = realpath(__DIR__ . '/../../uploads') ?: (__DIR__ . '/../../uploads');
        $recortesFichaDir = $uploadDir . '/recortes/ficha_' . $fichaId;
        if (!is_dir($recortesFichaDir)) {
            @mkdir($recortesFichaDir, 0777, true);
        }

        $origenImgDir = $uploadDir . '/tmp/' . $trabajoId . '/img';

        $this->db->prepare("DELETE FROM documentos_pdf_ocr WHERE ficha_id = :id")->execute(['id' => $fichaId]);
        $this->db->prepare("DELETE FROM cruce_conciliacion WHERE ficha_id = :id")->execute(['id' => $fichaId]);

        $stmtDoc = $this->db->prepare("
            INSERT INTO documentos_pdf_ocr (
                ficha_id, numero_pagina, tipo_documento, numero_documento,
                primer_nombre, segundo_nombre, primer_apellido, segundo_apellido,
                nombre_completo_ocr, fecha_nacimiento, rh,
                metodo_extraccion, confianza_score, ruta_imagen_recorte
            ) VALUES (
                :ficha_id, :pag, :tipo_doc, :num_doc,
                :p_nom, :s_nom, :p_ape, :s_ape,
                :nom_comp, :fnac, :rh,
                :metodo, :conf, :img
            )
        ");

        $stmtCruce = $this->db->prepare("
            INSERT INTO cruce_conciliacion (
                ficha_id, aspirante_excel_id, documento_pdf_id,
                estado_cruce, similitud_nombres_porcentaje, observaciones
            ) VALUES (
                :ficha_id, :excel_id, :pdf_id,
                :estado, :sim, :obs
            )
        ");

        $stmtAsp = $this->db->prepare("SELECT id, numero_documento, nombre_completo FROM aspirantes_excel WHERE ficha_id = :id");
        $stmtAsp->execute(['id' => $fichaId]);
        $aspirantesExcel = $stmtAsp->fetchAll();
        $excelPorNum = [];
        foreach ($aspirantesExcel as $asp) {
            $limpio = ltrim(preg_replace('/[^\d]/', '', $asp['numero_documento']), '0');
            $excelPorNum[$limpio] = $asp;
        }

        $sincronizados = 0;
        foreach ($personas as $p) {
            $vals = $p['valores'] ?? [];
            $pag = $p['paginas'][0]['pagina'] ?? 1;
            $imgNombre = $p['paginas'][0]['imagen'] ?? '';
            $numDoc = $vals['documento'] ?? '';
            $numLimpio = ltrim(preg_replace('/[^\d]/', '', $numDoc), '0');

            $rutaGuardada = '';
            if ($imgNombre) {
                $srcFile = $origenImgDir . '/' . $imgNombre;
                $destNombre = 'pag_' . sprintf('%03d', $pag) . '_' . ($numLimpio ?: 'doc') . '.jpg';
                $destFile = $recortesFichaDir . '/' . $destNombre;
                if (file_exists($srcFile)) {
                    @copy($srcFile, $destFile);
                    $rutaGuardada = 'uploads/recortes/ficha_' . $fichaId . '/' . $destNombre;
                } else {
                    $rutaGuardada = $imgNombre;
                }
            }

            $nombres = $vals['nombres'] ?? '';
            $apellidos = $vals['apellidos'] ?? '';
            $nombreCompleto = trim("{$nombres} {$apellidos}");

            $stmtDoc->execute([
                'ficha_id'  => $fichaId,
                'pag'       => $pag,
                'tipo_doc'  => $vals['tipo_documento'] ?? 'CC',
                'num_doc'   => $numDoc,
                'p_nom'     => $nombres,
                's_nom'     => '',
                'p_ape'     => $apellidos,
                's_ape'     => '',
                'nom_comp'  => $nombreCompleto,
                'fnac'      => $vals['nacimiento'] ?? null,
                'rh'        => $vals['rh'] ?? null,
                'metodo'    => 'OCR_RAPID_NEURAL',
                'conf'      => 0.98,
                'img'       => $rutaGuardada ?: $imgNombre
            ]);
            $docId = (int)$this->db->lastInsertId();

            $excelAsp = $excelPorNum[$numLimpio] ?? null;
            $estadoCruce = ($p['estado'] === 'ok') ? 'CONCILIADO' : (($p['estado'] === 'revisar') ? 'DIFERENCIA_NOMBRE' : 'SOBRANTE_PDF');
            $similitud = ($estadoCruce === 'CONCILIADO') ? 100.0 : (($estadoCruce === 'DIFERENCIA_NOMBRE') ? 75.0 : 0.0);

            $stmtCruce->execute([
                'ficha_id'  => $fichaId,
                'excel_id'  => $excelAsp['id'] ?? null,
                'pdf_id'    => $docId,
                'estado'    => $estadoCruce,
                'sim'       => $similitud,
                'obs'       => $p['estado_texto'] ?? 'Conciliado'
            ]);
            $sincronizados++;
        }

        $faltantes = $datos['faltantes'] ?? [];
        foreach ($faltantes as $f) {
            $numDoc = $f['documento'] ?? '';
            $numLimpio = ltrim(preg_replace('/[^\d]/', '', $numDoc), '0');
            $excelAsp = $excelPorNum[$numLimpio] ?? null;
            if ($excelAsp) {
                $stmtCruce->execute([
                    'ficha_id'  => $fichaId,
                    'excel_id'  => $excelAsp['id'],
                    'pdf_id'    => null,
                    'estado'    => 'FALTANTE_PDF',
                    'sim'       => 0.0,
                    'obs'       => 'No adjuntó cédula en PDF'
                ]);
            }
        }

        if ($segundos > 0) {
            $this->fichaModel->updateTiempoProcesamiento($fichaId, $segundos);
        }
        $this->fichaModel->updateEstado($fichaId, 'CRUCE_COMPLETADO');

        return [
            'success' => true,
            'sincronizados' => $sincronizados,
            'mensaje' => "Se han guardado {$sincronizados} documentos correctamente en la base de datos."
        ];
    }

    /**
     * Exporta el informe comparativo a Excel (.xlsx) directamente
     */
    public function exportarExcel(): void
    {
        $fichaId = (int)($_GET['ficha'] ?? 0);
        $trabajoId = $_GET['trabajo'] ?? '';

        if (!$fichaId && !$trabajoId) {
            die("Identificador de ficha o trabajo no especificado.");
        }

        $ficha = $fichaId ? $this->fichaModel->findById($fichaId) : null;
        $codigoFicha = $ficha ? $ficha['codigo_ficha'] : 'Reporte';

        // 1. Si hay un microservicio de Python disponible para este trabajo, pedir el archivo procesado
        if ($trabajoId) {
            $url = "http://127.0.0.1:5005/exportar/" . urlencode($trabajoId) . ".xlsx";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $excelData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && !empty($excelData)) {
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="Cruce_Cotejo_Ficha_' . $codigoFicha . '.xlsx"');
                header('Content-Length: ' . strlen($excelData));
                echo $excelData;
                exit;
            }
        }

        // 2. Si no hay trabajo activo o ya está en MySQL, construir el payload desde MySQL y solicitar el xlsx formateado
        $informe = $fichaId ? $this->cruceModel->getInformeCompleto($fichaId) : [];
        $personas = [];
        $faltantes = [];
        $estadoMap = [
            'CONCILIADO' => 'ok',
            'DIFERENCIA_NOMBRE' => 'revisar',
            'SOBRANTE_PDF' => 'sin_listado',
            'ILEGIBLE' => 'revisar'
        ];

        foreach ($informe as $r) {
            if ($r['estado_cruce'] === 'FALTANTE_PDF') {
                $faltantes[] = [
                    'documento' => $r['excel_num_doc'] ?: $r['ocr_num_doc'],
                    'tipo' => $r['excel_tipo_doc'] ?: 'CC',
                    'nombre_completo' => $r['excel_nombre'] ?: ''
                ];
                continue;
            }

            $pNom = trim(($r['ocr_primer_nombre'] ?? '') . ' ' . ($r['ocr_segundo_nombre'] ?? ''));
            $pApe = trim(($r['ocr_primer_apellido'] ?? '') . ' ' . ($r['ocr_segundo_apellido'] ?? ''));
            if (!$pNom && !$pApe) {
                $pNom = $r['ocr_nombre'] ?: $r['excel_nombre'] ?: '';
            }

            $personas[] = [
                'id' => count($personas),
                'estado' => $estadoMap[$r['estado_cruce']] ?? 'ok',
                'novedad' => $r['observaciones'] ?? '',
                'valores' => [
                    'tipo_documento' => $r['ocr_tipo_doc'] ?: 'CC',
                    'documento' => $r['ocr_num_doc'] ?: $r['excel_num_doc'],
                    'nombres' => $pNom,
                    'apellidos' => $pApe,
                    'nacimiento' => $r['ocr_nacimiento'] ?? '',
                    'rh' => $r['ocr_rh'] ?? ''
                ],
                'paginas' => [
                    ['pagina' => (int)($r['pdf_pagina'] ?: (count($personas) + 1))]
                ],
                'listado' => [
                    'documento' => $r['excel_num_doc'] ?? '',
                    'tipo_documento' => $r['excel_tipo_doc'] ?? 'CC',
                    'nombre_completo' => $r['excel_nombre'] ?? ''
                ]
            ];
        }

        // Generar archivo a través del módulo python_ocr o CSV descargable
        $payload = json_encode([
            'personas' => $personas,
            'faltantes' => $faltantes,
            'codigo_ficha' => $codigoFicha,
            'programa_formacion' => $ficha['programa_formacion'] ?? ''
        ]);

        $ch = curl_init('http://127.0.0.1:5005/api/exportar-directo/xlsx');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $excelData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && !empty($excelData)) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="Cruce_Cotejo_Ficha_' . $codigoFicha . '.xlsx"');
            header('Content-Length: ' . strlen($excelData));
            echo $excelData;
            exit;
        }

        // Respaldo CSV directo si el microservicio no estuviera disponible
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Cruce_Cotejo_Ficha_' . $codigoFicha . '.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Pág', 'Tipo (PDF)', 'Documento (PDF)', 'Nombres (PDF)', 'Apellidos (PDF)', 'Tipo (Excel)', 'Documento (Excel)', 'Nombre Completo (Excel)', 'Estado', 'Novedad'], ';');
        foreach ($personas as $p) {
            $vals = $p['valores'];
            $ref = $p['listado'];
            fputcsv($out, [
                $p['paginas'][0]['pagina'] ?? 1,
                $vals['tipo_documento'],
                $vals['documento'],
                $vals['nombres'],
                $vals['apellidos'],
                $ref['tipo_documento'],
                $ref['documento'],
                $ref['nombre_completo'],
                $p['estado'],
                $p['novedad']
            ], ';');
        }
        foreach ($faltantes as $f) {
            fputcsv($out, ['-', '-', '-', '-', '-', $f['tipo'], $f['documento'], $f['nombre_completo'], 'Solo en Excel', 'No adjuntó cédula'], ';');
        }
        fclose($out);
        exit;
    }
}
