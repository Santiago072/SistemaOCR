<?php

namespace App\Services;

use RuntimeException;

/**
 * Servicio Puente para Ejecutar el Worker OCR de Python de Forma Segura
 */
class OcrPythonBridgeService
{
    private string $pythonExec;
    private string $scriptPath;

    public function __construct()
    {
        $this->pythonExec = getenv('PYTHON_EXECUTABLE') ?: 'python';
        $this->scriptPath = realpath(__DIR__ . '/../../python_ocr/extractor.py') ?: '';
    }

    /**
     * Procesa un PDF ejecutando el extractor en Python y devuelve un array estructurado
     */
    public function processPdf(string $pdfAbsolutePath, string $outputDirAbsolutePath): array
    {
        if (!file_exists($pdfAbsolutePath)) {
            throw new RuntimeException("El archivo PDF no existe: {$pdfAbsolutePath}");
        }

        if (!file_exists($this->scriptPath)) {
            throw new RuntimeException("El script extractor de Python no fue encontrado en: {$this->scriptPath}");
        }

        if (!is_dir($outputDirAbsolutePath)) {
            mkdir($outputDirAbsolutePath, 0777, true);
        }

        // -X utf8 fuerza salida UTF-8 en Windows (evita CP1252 por defecto)
        $cmd = sprintf(
            '%s -X utf8 %s --pdf %s --output-dir %s 2>&1',
            escapeshellarg($this->pythonExec),
            escapeshellarg($this->scriptPath),
            escapeshellarg($pdfAbsolutePath),
            escapeshellarg($outputDirAbsolutePath)
        );

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        $rawOutput = implode("\n", $output);

        // Buscar el bloque JSON en la respuesta
        $jsonStart = strpos($rawOutput, '{');
        $jsonEnd = strrpos($rawOutput, '}');

        if ($jsonStart === false || $jsonEnd === false) {
            error_log("Error en Python OCR Worker: " . $rawOutput);
            throw new RuntimeException("Error al ejecutar el procesador OCR en Python. Salida: " . $rawOutput);
        }

        $jsonString = substr($rawOutput, $jsonStart, ($jsonEnd - $jsonStart) + 1);

        // Garantizar UTF-8 válido antes de decodificar (Windows puede enviar CP1252)
        if (!mb_check_encoding($jsonString, 'UTF-8')) {
            $jsonString = mb_convert_encoding($jsonString, 'UTF-8', 'Windows-1252');
        }

        $decoded = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Error al decodificar la respuesta JSON de Python: " . json_last_error_msg());
        }

        return $decoded;
    }
}
