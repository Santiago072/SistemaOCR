<?php

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * Modelo de Fichas de Formación
 */
class Ficha extends Model
{
    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM fichas ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM fichas WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $ficha = $stmt->fetch();
        return $ficha ?: null;
    }

    public function findByCodigo(string $codigo): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM fichas WHERE codigo_ficha = :codigo");
        $stmt->execute(['codigo' => trim($codigo)]);
        $ficha = $stmt->fetch();
        return $ficha ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO fichas (codigo_ficha, programa_formacion, total_inscritos, archivo_excel_nombre, archivo_pdf_nombre, estado)
            VALUES (:codigo_ficha, :programa_formacion, :total_inscritos, :archivo_excel_nombre, :archivo_pdf_nombre, :estado)
            ON DUPLICATE KEY UPDATE 
                programa_formacion = VALUES(programa_formacion),
                total_inscritos = VALUES(total_inscritos),
                archivo_excel_nombre = VALUES(archivo_excel_nombre),
                archivo_pdf_nombre = VALUES(archivo_pdf_nombre),
                estado = VALUES(estado)
        ");

        $stmt->execute([
            'codigo_ficha'         => $data['codigo_ficha'],
            'programa_formacion'   => $data['programa_formacion'],
            'total_inscritos'      => $data['total_inscritos'] ?? 0,
            'archivo_excel_nombre' => $data['archivo_excel_nombre'] ?? null,
            'archivo_pdf_nombre'   => $data['archivo_pdf_nombre'] ?? null,
            'estado'               => $data['estado'] ?? 'CARGADA'
        ]);

        $id = $this->db->lastInsertId();
        if (!$id) {
            $existing = $this->findByCodigo($data['codigo_ficha']);
            return $existing ? (int)$existing['id'] : 0;
        }
        return (int)$id;
    }

    public function updateEstado(int $id, string $estado): bool
    {
        $stmt = $this->db->prepare("UPDATE fichas SET estado = :estado WHERE id = :id");
        return $stmt->execute(['estado' => $estado, 'id' => $id]);
    }

    public function deleteById(int $id): bool
    {
        // Borrar registros vinculados
        $this->db->prepare("DELETE FROM cruce_conciliacion WHERE ficha_id = :id")->execute(['id' => $id]);
        $this->db->prepare("DELETE FROM documentos_pdf_ocr WHERE ficha_id = :id")->execute(['id' => $id]);
        $this->db->prepare("DELETE FROM aspirantes_excel WHERE ficha_id = :id")->execute(['id' => $id]);
        $this->db->prepare("DELETE FROM participantes_finales WHERE ficha_id = :id")->execute(['id' => $id]);
        $stmt = $this->db->prepare("DELETE FROM fichas WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
