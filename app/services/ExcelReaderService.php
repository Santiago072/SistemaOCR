<?php

namespace App\Services;

use RuntimeException;

/**
 * Servicio para Lectura y Extracción de Datos del Reporte Excel de Inscripciones
 */
class ExcelReaderService
{
    private string $pythonExec;
    private string $parserScript;

    public function __construct()
    {
        $posiblesRutas = [
            'C:\\Users\\Usuario\\AppData\\Local\\Programs\\Python\\Python313\\python.exe',
            realpath(__DIR__ . '/../../python/python.exe'),
            realpath(__DIR__ . '/../../python_ocr/python/python.exe'),
            realpath(__DIR__ . '/../../../Lector de cedulas/python/python.exe'),
        ];

        $ejecutableEncontrado = null;
        foreach ($posiblesRutas as $ruta) {
            if ($ruta && file_exists($ruta)) {
                $ejecutableEncontrado = $ruta;
                break;
            }
        }

        $this->pythonExec = $ejecutableEncontrado ?: (getenv('PYTHON_EXECUTABLE') ?: 'python');
        $path = realpath(__DIR__ . '/../../python_ocr/excel_parser.py');
        if (!$path) {
            $path = __DIR__ . '/../../python_ocr/excel_parser.py';
        }
        $this->parserScript = $path;
    }

    /**
     * Parsea un archivo Excel de inscripción y retorna el array estructurado con la metadata y aspirantes
     */
    public function parseInscripciones(string $excelAbsolutePath): array
    {
        if (!file_exists($excelAbsolutePath)) {
            throw new RuntimeException("El archivo Excel no existe: {$excelAbsolutePath}");
        }

        if (!file_exists($this->parserScript)) {
            throw new RuntimeException("El script parser de Excel no fue encontrado en: {$this->parserScript}");
        }

        // -X utf8 fuerza salida UTF-8 en Windows (evita CP1252 por defecto)
        $cmd = sprintf(
            '%s -X utf8 %s --excel %s 2>&1',
            escapeshellarg($this->pythonExec),
            escapeshellarg($this->parserScript),
            escapeshellarg($excelAbsolutePath)
        );

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        $rawOutput = implode("\n", $output);

        $jsonStart = strpos($rawOutput, '{');
        $jsonEnd = strrpos($rawOutput, '}');

        if ($jsonStart === false || $jsonEnd === false) {
            error_log("Error en Excel Parser Worker: " . $rawOutput);
            throw new RuntimeException("Error al parsear el archivo Excel. Salida: " . $rawOutput);
        }

        $jsonString = substr($rawOutput, $jsonStart, ($jsonEnd - $jsonStart) + 1);

        // Garantizar UTF-8 válido (Windows puede enviar CP1252)
        if (!mb_check_encoding($jsonString, 'UTF-8')) {
            $jsonString = mb_convert_encoding($jsonString, 'UTF-8', 'Windows-1252');
        }

        $decoded = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($decoded['status']) || $decoded['status'] !== 'success') {
            $msg = $decoded['message'] ?? 'Error desconocido al procesar el Excel.';
            throw new RuntimeException($msg);
        }

        return $decoded['data'];
    }
}
