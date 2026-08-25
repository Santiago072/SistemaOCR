<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Motor de Cruce y Conciliación Documental (Excel vs OCR/PDF)
 */
class MatchingService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Ejecuta el cruce completo para una ficha específica
     */
    public function ejecutarCruce(int $fichaId): array
    {
        // 1. Obtener aspirantes del Excel
        $stmtExcel = $this->db->prepare("SELECT * FROM aspirantes_excel WHERE ficha_id = :ficha_id");
        $stmtExcel->execute(['ficha_id' => $fichaId]);
        $aspirantesExcel = $stmtExcel->fetchAll();

        // 2. Obtener documentos extraídos del PDF
        $stmtOcr = $this->db->prepare("SELECT * FROM documentos_pdf_ocr WHERE ficha_id = :ficha_id");
        $stmtOcr->execute(['ficha_id' => $fichaId]);
        $documentosOcr = $stmtOcr->fetchAll();

        // Limpiar cruces previos de esta ficha
        $del = $this->db->prepare("DELETE FROM cruce_conciliacion WHERE ficha_id = :ficha_id");
        $del->execute(['ficha_id' => $fichaId]);

        $ocrIndexados = [];
        $todosLosDocsPorNumero = [];

        foreach ($documentosOcr as $doc) {
            $numLimpio = $this->normalizarNumeroDocumento($doc['numero_documento'] ?? '');
            if ($numLimpio) {
                // Si el mismo número de cédula aparece en más de una página (ej. Pág 12 Frente y Pág 13 Reverso con PDF417):
                // Priorizar el registro que tenga el método PDF417 o que tenga nombre más completo
                if (!isset($ocrIndexados[$numLimpio])) {
                    $ocrIndexados[$numLimpio] = $doc;
                } else {
                    $actual = $ocrIndexados[$numLimpio];
                    $esActualPdf417 = ($actual['metodo_extraccion'] ?? '') === 'PDF417';
                    $esNuevoPdf417 = ($doc['metodo_extraccion'] ?? '') === 'PDF417';
                    
                    if (!$esActualPdf417 && $esNuevoPdf417) {
                        $ocrIndexados[$numLimpio] = $doc;
                    } elseif (strlen($doc['nombre_completo_ocr'] ?? '') > strlen($actual['nombre_completo_ocr'] ?? '')) {
                        $ocrIndexados[$numLimpio] = $doc;
                    }
                }
                $todosLosDocsPorNumero[$numLimpio][] = $doc['id'];
            }
        }

        $ocrUsados = [];
        $crucesAInsertar = [];

        // 3. Procesar cada aspirante del Excel
        foreach ($aspirantesExcel as $asp) {
            $numExcel = $this->normalizarNumeroDocumento($asp['numero_documento']);
            $docOcr = null;

            // Coincidencia exacta
            if (isset($ocrIndexados[$numExcel])) {
                $docOcr = $ocrIndexados[$numExcel];
            } else {
                // Coincidencia por terminación o prefijo de cédula antigua (ej. 1006501709 y 6501709)
                foreach ($ocrIndexados as $numKey => $candDoc) {
                    if (!isset($ocrUsados[$candDoc['id']])) {
                        if (str_ends_with($numExcel, $numKey) || str_ends_with($numKey, $numExcel)) {
                            // Validar que el nombre coincida
                            $nExcel = $this->normalizarTexto($asp['nombre_completo']);
                            $nOcr = $this->normalizarTexto($candDoc['nombre_completo_ocr'] ?? '');
                            similar_text($nExcel, $nOcr, $pNom);
                            if ($pNom >= 60.0) {
                                $docOcr = $candDoc;
                                $numExcel = $numKey; // Usar la clave encontrada
                                break;
                            }
                        }
                    }
                }
            }

            if ($docOcr) {
                // Marcar todas las páginas (frente y reverso) asociadas a este número de cédula como usadas
                if (isset($todosLosDocsPorNumero[$numExcel])) {
                    foreach ($todosLosDocsPorNumero[$numExcel] as $dId) {
                        $ocrUsados[$dId] = true;
                    }
                } else {
                    $ocrUsados[$docOcr['id']] = true;
                }

                // Calcular similitud de nombres
                $nombreExcel = $this->normalizarTexto($asp['nombre_completo']);
                $nombreOcr = $this->normalizarTexto($docOcr['nombre_completo_ocr'] ?? '');
                
                $similitud = 0.0;
                if (!empty($nombreExcel) && !empty($nombreOcr)) {
                    similar_text($nombreExcel, $nombreOcr, $porcentaje);
                    $similitud = round($porcentaje, 2);

                    // Si el OCR visual solo capturó parte del nombre (ej: "JHON JAMEZ" dentro de "JHON JAMEZ ANDRADE CADENA")
                    $palabrasOcr = explode(' ', $nombreOcr);
                    $palabrasExcel = explode(' ', $nombreExcel);
                    $coincidencias = 0;
                    foreach ($palabrasOcr as $p) {
                        if (strlen($p) >= 3 && in_array($p, $palabrasExcel)) {
                            $coincidencias++;
                        }
                    }
                    if (count($palabrasOcr) > 0 && ($coincidencias / count($palabrasOcr)) >= 0.75) {
                        $similitud = max($similitud, 85.0);
                    }
                }

                // Si el número de cédula coincide exactamente y hay similitud aceptable o se leyó por PDF417
                $esPdf417 = ($docOcr['metodo_extraccion'] ?? '') === 'PDF417';
                if ($similitud >= 70.0 || ($esPdf417 && $similitud >= 50.0)) {
                    $estado = 'CONCILIADO';
                    $obs = "Coincidencia exacta de documento y alta similitud en nombres ({$similitud}%).";
                } else {
                    $estado = 'DIFERENCIA_NOMBRE';
                    $obs = "Documento coincide ({$numExcel}), pero hay diferencia en nombres: Excel: '{$asp['nombre_completo']}' vs OCR: '{$docOcr['nombre_completo_ocr']}' (Similitud: {$similitud}%).";
                }

                $crucesAInsertar[] = [
                    'ficha_id'                    => $fichaId,
                    'aspirante_excel_id'          => $asp['id'],
                    'documento_pdf_id'            => $docOcr['id'],
                    'estado_cruce'                => $estado,
                    'similitud_nombres_porcentaje'=> $similitud,
                    'observaciones'               => $obs
                ];
            } else {
                // Faltante en PDF
                $crucesAInsertar[] = [
                    'ficha_id'                    => $fichaId,
                    'aspirante_excel_id'          => $asp['id'],
                    'documento_pdf_id'            => null,
                    'estado_cruce'                => 'FALTANTE_PDF',
                    'similitud_nombres_porcentaje'=> 0.0,
                    'observaciones'               => "El participante está en el reporte de Excel pero no se encontró su documento en el PDF."
                ];
            }
        }

        // 4. Identificar documentos del PDF sobrantes o no asociados al Excel
        foreach ($documentosOcr as $docOcr) {
            if (!isset($ocrUsados[$docOcr['id']])) {
                $docNumNorm = $this->normalizarNumeroDocumento($docOcr['numero_documento'] ?? '');
                $docNomNorm = $this->normalizarTexto($docOcr['nombre_completo_ocr'] ?? '');

                // Verificar si esta página corresponde al reverso o lectura parcial de un aspirante ya conciliado
                $esDuplicadoDeConciliado = false;
                foreach ($aspirantesExcel as $asp) {
                    $aspNum = $this->normalizarNumeroDocumento($asp['numero_documento']);
                    $aspNom = $this->normalizarTexto($asp['nombre_completo']);

                    // Si el número es subcadena/prefijo del aspirante o tiene distancia mínima por error de OCR en 1 dígito (ej: 1117563913 vs 1117553913)
                    if ($docNumNorm) {
                        if (str_ends_with($aspNum, $docNumNorm) || str_ends_with($docNumNorm, $aspNum) || (strlen($docNumNorm) >= 6 && str_contains($aspNum, $docNumNorm))) {
                            $esDuplicadoDeConciliado = true;
                            break;
                        }
                        if (strlen($aspNum) >= 8 && strlen($docNumNorm) >= 8 && levenshtein($aspNum, $docNumNorm) <= 2) {
                            $esDuplicadoDeConciliado = true;
                            break;
                        }
                    }
                    if (!empty($docNomNorm) && !empty($aspNom)) {
                        similar_text($aspNom, $docNomNorm, $simRev);
                        if ($simRev >= 70.0) {
                            $esDuplicadoDeConciliado = true;
                            break;
                        }
                    }
                }

                if ($esDuplicadoDeConciliado) {
                    // Ignorar porque es la otra cara (reverso/frente) de una cédula ya conciliada
                    continue;
                }

                $estado = empty($docOcr['numero_documento']) ? 'ILEGIBLE' : 'SOBRANTE_PDF';
                $obs = ($estado === 'ILEGIBLE') 
                    ? "La imagen de la página {$docOcr['numero_pagina']} no se pudo leer con suficiente claridad."
                    : "Cédula No. {$docOcr['numero_documento']} presente en el PDF pero no está en la lista de inscritos de esta ficha.";

                $crucesAInsertar[] = [
                    'ficha_id'                    => $fichaId,
                    'aspirante_excel_id'          => null,
                    'documento_pdf_id'            => $docOcr['id'],
                    'estado_cruce'                => $estado,
                    'similitud_nombres_porcentaje'=> 0.0,
                    'observaciones'               => $obs
                ];
            }
        }

        // 5. Guardar en Base de Datos
        $this->guardarCruces($crucesAInsertar);

        // Actualizar estado de la ficha
        $upd = $this->db->prepare("UPDATE fichas SET estado = 'CRUCE_COMPLETADO' WHERE id = :id");
        $upd->execute(['id' => $fichaId]);

        return $crucesAInsertar;
    }

    private function guardarCruces(array $cruces): void
    {
        if (empty($cruces)) return;

        $sql = "INSERT INTO cruce_conciliacion (ficha_id, aspirante_excel_id, documento_pdf_id, estado_cruce, similitud_nombres_porcentaje, observaciones) VALUES ";
        $placeholders = [];
        $values = [];

        foreach ($cruces as $i => $c) {
            $placeholders[] = "(:f_{$i}, :asp_{$i}, :doc_{$i}, :est_{$i}, :sim_{$i}, :obs_{$i})";
            $values["f_{$i}"]   = $c['ficha_id'];
            $values["asp_{$i}"] = $c['aspirante_excel_id'];
            $values["doc_{$i}"] = $c['documento_pdf_id'];
            $values["est_{$i}"] = $c['estado_cruce'];
            $values["sim_{$i}"] = $c['similitud_nombres_porcentaje'];
            $values["obs_{$i}"] = $c['observaciones'];
        }

        $sql .= implode(', ', $placeholders);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
    }

    private function normalizarNumeroDocumento(?string $num): string
    {
        if (!$num) return '';
        // Quitar puntos, comas, espacios y ceros a la izquierda innecesarios
        $limpio = preg_replace('/[^\d]/', '', $num);
        return ltrim($limpio, '0');
    }

    private function normalizarTexto(?string $text): string
    {
        if (!$text) return '';
        $text = strtoupper(trim($text));
        // Reemplazar acentos
        $unaccented = strtr($text, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N'
        ]);
        // Normalizar partículas compuestas comunes (ej: DELA -> DE LA, DELOS -> DE LOS)
        $unaccented = preg_replace('/\bDELA\b/', 'DE LA', $unaccented);
        $unaccented = preg_replace('/\bDELOS\b/', 'DE LOS', $unaccented);
        $unaccented = preg_replace('/\bDELAS\b/', 'DE LAS', $unaccented);

        // Quitar caracteres especiales
        $clean = preg_replace('/[^A-Z0-9\s]/', '', $unaccented);
        return preg_replace('/\s+/', ' ', $clean);
    }
}
