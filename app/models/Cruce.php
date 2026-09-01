<?php

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * Modelo de Cruce y Conciliación
 */
class Cruce extends Model
{
    public function getInformeCompleto(int $fichaId): array
    {
        $sql = "
            SELECT 
                c.id as cruce_id,
                c.ficha_id,
                c.estado_cruce,
                c.similitud_nombres_porcentaje,
                c.observaciones,
                c.validado_manualmente,
                -- Datos del Excel
                ae.id as excel_id,
                ae.tipo_documento as excel_tipo_doc,
                ae.numero_documento as excel_num_doc,
                ae.nombre_completo as excel_nombre,
                ae.estado_inscripcion as excel_estado,
                -- Datos del OCR
                dpo.id as ocr_id,
                dpo.numero_pagina as pdf_pagina,
                dpo.tipo_documento as ocr_tipo_doc,
                dpo.numero_documento as ocr_num_doc,
                dpo.primer_nombre as ocr_primer_nombre,
                dpo.segundo_nombre as ocr_segundo_nombre,
                dpo.primer_apellido as ocr_primer_apellido,
                dpo.segundo_apellido as ocr_segundo_apellido,
                dpo.nombre_completo_ocr as ocr_nombre,
                dpo.fecha_nacimiento as ocr_nacimiento,
                dpo.rh as ocr_rh,
                dpo.metodo_extraccion,
                dpo.confianza_score,
                dpo.ruta_imagen_recorte
            FROM cruce_conciliacion c
            LEFT JOIN aspirantes_excel ae ON c.aspirante_excel_id = ae.id
            LEFT JOIN documentos_pdf_ocr dpo ON c.documento_pdf_id = dpo.id
            WHERE c.ficha_id = :ficha_id
            ORDER BY 
                CASE c.estado_cruce
                    WHEN 'DIFERENCIA_NOMBRE' THEN 1
                    WHEN 'FALTANTE_PDF' THEN 2
                    WHEN 'ILEGIBLE' THEN 3
                    WHEN 'SOBRANTE_PDF' THEN 4
                    WHEN 'CONCILIADO' THEN 5
                END ASC,
                ae.id ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['ficha_id' => $fichaId]);
        return $stmt->fetchAll();
    }

    public function getEstadisticas(int $fichaId): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_registros,
                SUM(CASE WHEN estado_cruce = 'CONCILIADO' THEN 1 ELSE 0 END) as conciliados,
                SUM(CASE WHEN estado_cruce = 'DIFERENCIA_NOMBRE' THEN 1 ELSE 0 END) as diferencias,
                SUM(CASE WHEN estado_cruce = 'FALTANTE_PDF' THEN 1 ELSE 0 END) as faltantes,
                SUM(CASE WHEN estado_cruce = 'SOBRANTE_PDF' THEN 1 ELSE 0 END) as sobrantes,
                SUM(CASE WHEN estado_cruce = 'ILEGIBLE' THEN 1 ELSE 0 END) as ilegibles
            FROM cruce_conciliacion
            WHERE ficha_id = :ficha_id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['ficha_id' => $fichaId]);
        $stats = $stmt->fetch();

        return [
            'total'       => (int)($stats['total_registros'] ?? 0),
            'conciliados' => (int)($stats['conciliados'] ?? 0),
            'diferencias' => (int)($stats['diferencias'] ?? 0),
            'faltantes'   => (int)($stats['faltantes'] ?? 0),
            'sobrantes'   => (int)($stats['sobrantes'] ?? 0),
            'ilegibles'   => (int)($stats['ilegibles'] ?? 0),
        ];
    }
}
