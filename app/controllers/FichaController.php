<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Ficha;
use App\Models\AspiranteExcel;
use App\Models\DocumentoPdfOcr;
use App\Services\ExcelReaderService;
use App\Services\OcrPythonBridgeService;
use App\Services\MatchingService;
use RuntimeException;

/**
 * Controlador de Carga y Procesamiento de Fichas (Excel + PDF)
 */
class FichaController extends Controller
{
    private Ficha $fichaModel;
    private AspiranteExcel $aspiranteModel;
    private DocumentoPdfOcr $documentoModel;
    private ExcelReaderService $excelService;
    private OcrPythonBridgeService $ocrService;
    private MatchingService $matchingService;

    public function __construct(
        Ficha $fichaModel,
        AspiranteExcel $aspiranteModel,
        DocumentoPdfOcr $documentoModel,
        ExcelReaderService $excelService,
        OcrPythonBridgeService $ocrService,
        MatchingService $matchingService
    ) {
        $this->fichaModel = $fichaModel;
        $this->aspiranteModel = $aspiranteModel;
        $this->documentoModel = $documentoModel;
        $this->excelService = $excelService;
        $this->ocrService = $ocrService;
        $this->matchingService = $matchingService;
    }

    /**
     * Muestra la vista de subida de archivos
     */
    public function subir(): void
    {
        $this->view('fichas/subir', [
            'titulo' => 'Cargar Ficha y Documentación',
            'extraCss' => ['dropzone.css'],
            'extraJs' => ['dropzone-uploader.js']
        ]);
    }

    /**
     * Procesa la carga de archivos Excel y PDF y ejecuta la extracción y cruce
     */
    public function procesar(): void
    {
        // Ampliar tiempo de ejecución y memoria para procesamiento OCR de PDFs extensos
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new RuntimeException("Método no permitido.");
            }

            if (!isset($_FILES['archivo_excel']) || $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException("Por favor seleccione un archivo Excel válido.");
            }

            if (!isset($_FILES['archivo_pdf']) || $_FILES['archivo_pdf']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException("Por favor seleccione un archivo PDF de documentación válido.");
            }

            // Validar extensiones y tipos MIME de forma segura
            $excelFile = $_FILES['archivo_excel'];
            $pdfFile = $_FILES['archivo_pdf'];

            $excelExt = strtolower(pathinfo($excelFile['name'], PATHINFO_EXTENSION));
            $pdfExt = strtolower(pathinfo($pdfFile['name'], PATHINFO_EXTENSION));

            if (!in_array($excelExt, ['xlsx', 'xls'])) {
                throw new RuntimeException("El archivo de inscripciones debe ser formato Excel (.xlsx o .xls).");
            }

            if ($pdfExt !== 'pdf') {
                throw new RuntimeException("El archivo de documentos debe ser formato PDF (.pdf).");
            }

            // Crear directorios de subida seguros
            $uploadDir = realpath(__DIR__ . '/../../uploads') ?: (__DIR__ . '/../../uploads');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $uniqueId = time() . '_' . bin2hex(random_bytes(4));
            $savedExcelPath = $uploadDir . '/' . $uniqueId . '_' . basename($excelFile['name']);
            $savedPdfPath = $uploadDir . '/' . $uniqueId . '_' . basename($pdfFile['name']);

            move_uploaded_file($excelFile['tmp_name'], $savedExcelPath);
            move_uploaded_file($pdfFile['tmp_name'], $savedPdfPath);

            $startTime = microtime(true);

            // 1. Procesar Excel
            $excelData = $this->excelService->parseInscripciones($savedExcelPath);
            $codigoFicha = $excelData['codigo_ficha'] ?? 'SIN_CODIGO';
            $programa = $excelData['programa_formacion'] ?? 'PROGRAMA NO ESPECIFICADO';
            $aspirantes = $excelData['aspirantes'] ?? [];

            // Si la ficha ya existía previamente, eliminar sus archivos viejos de uploads para no duplicar espacio
            $fichaPrevia = $this->fichaModel->findByCodigo($codigoFicha);
            if ($fichaPrevia) {
                if (!empty($fichaPrevia['archivo_excel_nombre'])) {
                    foreach (glob($uploadDir . '/*_' . $fichaPrevia['archivo_excel_nombre']) as $a) {
                        if ($a !== $savedExcelPath) { @unlink($a); }
                    }
                }
                if (!empty($fichaPrevia['archivo_pdf_nombre'])) {
                    foreach (glob($uploadDir . '/*_' . $fichaPrevia['archivo_pdf_nombre']) as $a) {
                        if ($a !== $savedPdfPath) { @unlink($a); }
                    }
                }
            }

            // 2. Guardar o actualizar Ficha en BD
            $fichaId = $this->fichaModel->create([
                'codigo_ficha'         => $codigoFicha,
                'programa_formacion'   => $programa,
                'total_inscritos'      => count($aspirantes),
                'archivo_excel_nombre' => basename($excelFile['name']),
                'archivo_pdf_nombre'   => basename($pdfFile['name']),
                'estado'               => 'PROCESANDO_OCR'
            ]);

            // 3. Guardar aspirantes del Excel en BD
            $this->aspiranteModel->insertBatch($fichaId, $aspirantes);

            // 4. Iniciar trabajo en el microservicio asíncrono en segundo plano (retorna en < 500ms)
            $trabajo = $this->ocrService->iniciarTrabajo($savedPdfPath, $savedExcelPath);
            $trabajoId = $trabajo['trabajo'] ?? '';

            // Responder con éxito y redirección inmediata a la vista de revisión en vivo
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                $this->json([
                    'success' => true,
                    'ficha_id' => $fichaId,
                    'trabajo_id' => $trabajoId,
                    'redirect_url' => defined('BASE_PATH') ? BASE_PATH . "index.php?ruta=cruce/informe&ficha={$fichaId}&trabajo={$trabajoId}" : "index.php?ruta=cruce/informe&ficha={$fichaId}&trabajo={$trabajoId}"
                ]);
            } else {
                $this->redirect("cruce/informe&ficha={$fichaId}&trabajo={$trabajoId}");
            }

        } catch (\Throwable $e) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                $this->json(['success' => false, 'error' => $e->getMessage()], 400);
            } else {
                echo "<div style='color:red; padding:20px; font-family:sans-serif;'><h3>Error:</h3>" . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }

    /**
     * Elimina una ficha procesada y sus registros de la BD y del disco para poder volver a procesarla
     */
    public function eliminar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método no permitido'], 405);
        }

        $fichaId = (int)($_POST['ficha_id'] ?? 0);
        if (!$fichaId) {
            $this->json(['error' => 'ID de ficha no válido'], 400);
        }

        $uploadDir = realpath(__DIR__ . '/../../uploads') ?: (__DIR__ . '/../../uploads');
        $recortesDir = $uploadDir . '/recortes/ficha_' . $fichaId;

        // Obtener datos de la ficha para borrar sus archivos PDF y Excel en uploads/
        $ficha = $this->fichaModel->findById($fichaId);
        if ($ficha) {
            if (!empty($ficha['archivo_excel_nombre'])) {
                $archivosExcel = glob($uploadDir . '/*' . $ficha['archivo_excel_nombre']);
                foreach ($archivosExcel as $a) { if (is_file($a)) { @unlink($a); } }
            }
            if (!empty($ficha['archivo_pdf_nombre'])) {
                $archivosPdf = glob($uploadDir . '/*' . $ficha['archivo_pdf_nombre']);
                foreach ($archivosPdf as $a) { if (is_file($a)) { @unlink($a); } }
            }
        }

        // Borrar imágenes de recortes de la ficha en uploads/recortes/ficha_X
        if (is_dir($recortesDir)) {
            $files = glob($recortesDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($recortesDir);
        }

        // Limpiar de forma exhaustiva carpetas temporales huérfanas en uploads/tmp/
        $tmpDir = $uploadDir . '/tmp';
        if (is_dir($tmpDir)) {
            $trabajos = glob($tmpDir . '/*');
            foreach ($trabajos as $t) {
                if (is_dir($t)) {
                    $subFiles = glob($t . '/*');
                    foreach ($subFiles as $sf) {
                        if (is_dir($sf)) {
                            foreach (glob($sf . '/*') as $ssf) { @unlink($ssf); }
                            @rmdir($sf);
                        } else {
                            @unlink($sf);
                        }
                    }
                    @rmdir($t);
                }
            }
        }

        // Borrar de la BD
        $exito = $this->fichaModel->deleteById($fichaId);

        $this->json([
            'success' => $exito,
            'mensaje' => 'La ficha y sus datos asociados han sido eliminados correctamente.'
        ]);
    }
}
