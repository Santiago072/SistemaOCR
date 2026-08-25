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

        $informe = $this->cruceModel->getInformeCompleto($fichaId);
        $estadisticas = $this->cruceModel->getEstadisticas($fichaId);

        $this->view('cruce/informe', [
            'titulo' => "Informe de Cruce - Ficha " . htmlspecialchars($ficha['codigo_ficha']),
            'ficha' => $ficha,
            'informe' => $informe,
            'estadisticas' => $estadisticas,
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
        foreach ($conciliados as $c) {
            $nombres = trim(($c['primer_nombre'] ?? '') . ' ' . ($c['segundo_nombre'] ?? ''));
            $apellidos = trim(($c['primer_apellido'] ?? '') . ' ' . ($c['segundo_apellido'] ?? ''));
            
            if (empty($nombres) && empty($apellidos)) {
                $nombres = $c['nombre_completo'];
                $apellidos = '';
            }

            $stmtInsert->execute([
                'ficha_id'         => $fichaId,
                'tipo_doc'         => $c['tipo_documento'],
                'num_doc'          => $c['numero_documento'],
                'nombres'          => $nombres,
                'apellidos'        => $apellidos,
                'nombre_completo'  => $c['nombre_completo'],
                'genero'           => $c['genero'],
                'fnac'             => $c['fecha_nacimiento'],
                'rh'               => $c['rh'],
                'estado_ins'       => $c['estado_inscripcion'] ?: 'Matriculado / Validado',
                'origen'           => $c['validado_manualmente'] ? 'MANUAL_SUPERVISOR' : 'AUTOMATICO_OCR'
            ]);
            $importados++;
        }

        // Actualizar estado de la ficha a IMPORTADA
        $this->fichaModel->updateEstado($fichaId, 'IMPORTADA');

        $this->json([
            'success' => true,
            'mensaje' => "Se importaron exitosamente {$importados} participantes a la base de datos oficial.",
            'total_importados' => $importados
        ]);
    }
}
