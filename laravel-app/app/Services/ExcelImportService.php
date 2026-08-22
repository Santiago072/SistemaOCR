<?php

namespace App\Services;

use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class ExcelImportService
{
    /**
     * Parsea el archivo Excel del reporte de inscripción de Sofía Plus / SENA.
     * Retorna array con 'codigo_ficha', 'programa_formacion' y lista de 'aspirantes'.
     */
    public function parseInscripciones(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("El archivo Excel no fue encontrado en: {$filePath}");
        }

        $sheets = Excel::toArray([], $filePath);
        if (empty($sheets) || empty($sheets[0])) {
            throw new RuntimeException("El archivo Excel está vacío o no tiene formato válido.");
        }

        $rows = $sheets[0];
        $codigoFicha = 'SIN_CODIGO';
        $programaFormacion = 'PROGRAMA NO ESPECIFICADO';
        $aspirantes = [];
        $headerFound = false;

        foreach ($rows as $index => $row) {
            $rowValues = array_map(function ($val) {
                return trim((string)$val);
            }, $row);

            $rowText = strtoupper(implode(' ', $rowValues));

            // Extraer Código de Ficha del encabezado
            if (str_contains($rowText, 'CODIGO') || str_contains($rowText, 'CÓDIGO') || str_contains($rowText, 'FICHA')) {
                foreach ($rowValues as $cell) {
                    if (preg_match('/\b\d{6,8}\b/', $cell, $matches)) {
                        $codigoFicha = $matches[0];
                    }
                }
            }

            // Extraer Programa de Formación
            if (str_contains($rowText, 'PROGRAMA')) {
                foreach ($rowValues as $cell) {
                    if (strlen($cell) > 5 && !str_contains(strtoupper($cell), 'PROGRAMA')) {
                        $programaFormacion = $cell;
                    }
                }
            }

            // Detectar inicio de la tabla de participantes
            if (str_contains($rowText, 'IDENTIFIC') || (str_contains($rowText, 'NOMBRE') && str_contains($rowText, 'ESTADO'))) {
                $headerFound = true;
                continue;
            }

            if ($headerFound && !empty(array_filter($rowValues))) {
                $aspirante = $this->parseAspiranteRow($rowValues);
                if ($aspirante) {
                    $aspirantes[] = $aspirante;
                }
            }
        }

        return [
            'codigo_ficha'       => $codigoFicha,
            'programa_formacion' => $programaFormacion,
            'total_aspirantes'   => count($aspirantes),
            'aspirantes'         => $aspirantes
        ];
    }

    private function parseAspiranteRow(array $row): ?array
    {
        $idCell = $row[0] ?? '';
        $nombreCell = $row[1] ?? '';
        $estadoCell = $row[2] ?? 'Seleccionado';

        if (empty($idCell) && empty($nombreCell)) {
            return null;
        }

        $tipoDoc = 'CC';
        $numeroDoc = '';

        if (str_contains($idCell, '-')) {
            $parts = explode('-', $idCell, 2);
            $tipoDoc = strtoupper(trim($parts[0]));
            $numeroDoc = preg_replace('/[^\d]/', '', $parts[1]);
        } else {
            $numeroDoc = preg_replace('/[^\d]/', '', $idCell);
            if (preg_match('/\b(CC|TI|CE|PEP|PPT|PAS)\b/i', $idCell, $m)) {
                $tipoDoc = strtoupper($m[1]);
            }
        }

        if (empty($numeroDoc)) {
            return null;
        }

        $nombreLimpio = preg_replace('/\s+/', ' ', trim($nombreCell));

        return [
            'tipo_documento'     => $tipoDoc,
            'numero_documento'   => $numeroDoc,
            'nombre_completo'    => strtoupper($nombreLimpio),
            'estado_inscripcion' => $estadoCell ?: 'Seleccionado'
        ];
    }
}
