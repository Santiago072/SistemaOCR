<?php

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * Modelo de Aspirantes del Excel
 */
class AspiranteExcel extends Model
{
    public function getByFichaId(int $fichaId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM aspirantes_excel WHERE ficha_id = :ficha_id ORDER BY id ASC");
        $stmt->execute(['ficha_id' => $fichaId]);
        return $stmt->fetchAll();
    }

    public function insertBatch(int $fichaId, array $aspirantes): bool
    {
        if (empty($aspirantes)) {
            return false;
        }

        // Eliminar aspirantes previos de esta ficha para recarga limpia
        $del = $this->db->prepare("DELETE FROM aspirantes_excel WHERE ficha_id = :ficha_id");
        $del->execute(['ficha_id' => $fichaId]);

        $sql = "INSERT INTO aspirantes_excel (ficha_id, tipo_documento, numero_documento, nombre_completo, estado_inscripcion) VALUES ";
        $placeholders = [];
        $values = [];

        foreach ($aspirantes as $index => $asp) {
            $placeholders[] = "(:ficha_{$index}, :tipo_{$index}, :num_{$index}, :nom_{$index}, :est_{$index})";
            $values["ficha_{$index}"] = $fichaId;
            $values["tipo_{$index}"]  = $asp['tipo_documento'] ?? 'CC';
            $values["num_{$index}"]   = $asp['numero_documento'];
            $values["nom_{$index}"]   = $asp['nombre_completo'];
            $values["est_{$index}"]   = $asp['estado_inscripcion'] ?? 'Preinscrito';
        }

        $sql .= implode(', ', $placeholders);
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }
}
