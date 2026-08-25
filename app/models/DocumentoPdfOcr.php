<?php

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * Modelo de Documentos Extraídos por OCR / PDF417
 */
class DocumentoPdfOcr extends Model
{
    public function getByFichaId(int $fichaId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM documentos_pdf_ocr WHERE ficha_id = :ficha_id ORDER BY numero_pagina ASC");
        $stmt->execute(['ficha_id' => $fichaId]);
        return $stmt->fetchAll();
    }

    public function insertBatch(int $fichaId, array $documentos): bool
    {
        if (empty($documentos)) {
            return false;
        }

        // Limpiar extracciones anteriores de esta ficha si existen
        $del = $this->db->prepare("DELETE FROM documentos_pdf_ocr WHERE ficha_id = :ficha_id");
        $del->execute(['ficha_id' => $fichaId]);

        $sql = "INSERT INTO documentos_pdf_ocr 
                (ficha_id, numero_pagina, tipo_documento, numero_documento, primer_apellido, segundo_apellido, primer_nombre, segundo_nombre, nombre_completo_ocr, genero, fecha_nacimiento, rh, metodo_extraccion, confianza_score, ruta_imagen_recorte, raw_data_json) 
                VALUES ";
        
        $placeholders = [];
        $values = [];

        foreach ($documentos as $i => $doc) {
            $placeholders[] = "(:f_{$i}, :pag_{$i}, :tipo_{$i}, :num_{$i}, :ape1_{$i}, :ape2_{$i}, :nom1_{$i}, :nom2_{$i}, :nom_comp_{$i}, :gen_{$i}, :fnac_{$i}, :rh_{$i}, :met_{$i}, :conf_{$i}, :img_{$i}, :raw_{$i})";
            
            $values["f_{$i}"]        = $fichaId;
            $values["pag_{$i}"]      = $doc['numero_pagina'] ?? ($i + 1);
            $values["tipo_{$i}"]     = $doc['tipo_documento'] ?? 'CC';
            $values["num_{$i}"]      = $doc['numero_documento'] ?? null;
            $values["ape1_{$i}"]     = $doc['primer_apellido'] ?? null;
            $values["ape2_{$i}"]     = $doc['segundo_apellido'] ?? null;
            $values["nom1_{$i}"]     = $doc['primer_nombre'] ?? null;
            $values["nom2_{$i}"]     = $doc['segundo_nombre'] ?? null;
            $values["nom_comp_{$i}"] = $doc['nombre_completo_ocr'] ?? null;
            $values["gen_{$i}"]      = $doc['genero'] ?? null;
            $values["fnac_{$i}"]     = $doc['fecha_nacimiento'] ?? null;
            $values["rh_{$i}"]       = $doc['rh'] ?? null;
            $values["met_{$i}"]      = $doc['metodo_extraccion'] ?? 'PDF417';
            $values["conf_{$i}"]     = $doc['confianza_score'] ?? 100.00;
            $values["img_{$i}"]      = $doc['ruta_imagen_recorte'] ?? null;
            $values["raw_{$i}"]      = isset($doc['raw_data_json']) ? json_encode($doc['raw_data_json'], JSON_UNESCAPED_UNICODE) : null;
        }

        $sql .= implode(', ', $placeholders);
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }
}
