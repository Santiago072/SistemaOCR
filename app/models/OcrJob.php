<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

/**
 * Modelo para gestionar OCR Jobs y su progreso en tiempo real
 * PASO 5: Monitoreo en tiempo real del procesamiento OCR
 */
class OcrJob extends Model
{
    public function __construct(?PDO $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
        } else {
            parent::__construct();
        }
    }

    /**
     * Crea un nuevo job OCR
     */
    public function create(string $jobId, ?int $fichaId = null, int $totalPages = 0): void
    {
        $sql = "INSERT INTO ocr_jobs (id, ficha_id, status, total_pages, current_page, started_at) 
                VALUES (:id, :ficha_id, 'PROCESSING', :total_pages, 0, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $jobId,
            ':ficha_id' => $fichaId,
            ':total_pages' => $totalPages
        ]);
    }

    /**
     * Obtiene un job por su ID
     */
    public function getById(string $jobId): ?array
    {
        $sql = "SELECT * FROM ocr_jobs WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $jobId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Obtiene job más reciente por ficha_id
     */
    public function getByFichaId(int $fichaId): ?array
    {
        $sql = "SELECT * FROM ocr_jobs WHERE ficha_id = :ficha_id ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':ficha_id' => $fichaId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Actualiza progreso del job
     */
    public function updateProgress(string $jobId, ?int $currentPage = null, ?string $currentPhase = null, ?int $documentsFound = null): void
    {
        $updates = [];
        $params = [':id' => $jobId];

        if ($currentPage !== null) {
            $updates[] = 'current_page = :current_page';
            $params[':current_page'] = $currentPage;
        }

        if ($currentPhase !== null) {
            $updates[] = 'current_phase = :current_phase';
            $params[':current_phase'] = $currentPhase;
        }

        if ($documentsFound !== null) {
            $updates[] = 'documents_found = :documents_found';
            $params[':documents_found'] = $documentsFound;
        }

        if (empty($updates)) {
            return;
        }

        $sql = "UPDATE ocr_jobs SET " . implode(", ", $updates) . " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Marca job como completado
     */
    public function markCompleted(string $jobId, ?int $documentsFound = null): void
    {
        $sql = "UPDATE ocr_jobs 
                SET status = 'COMPLETED', 
                    completed_at = NOW(), 
                    duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW())";
        
        if ($documentsFound !== null) {
            $sql .= ", documents_found = :documents_found";
        }
        
        $sql .= " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $params = [':id' => $jobId];
        if ($documentsFound !== null) {
            $params[':documents_found'] = $documentsFound;
        }
        
        $stmt->execute($params);
    }

    /**
     * Marca job con error
     */
    public function markError(string $jobId, string $errorMessage): void
    {
        $sql = "UPDATE ocr_jobs 
                SET status = 'ERROR', 
                    error_message = :error_message,
                    completed_at = NOW(),
                    duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW())
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $jobId,
            ':error_message' => $errorMessage
        ]);
    }

    /**
     * Registra un evento de progreso
     */
    public function logEvent(string $jobId, string $eventType, ?string $message = null, 
                            ?int $currentPage = null, ?int $totalPages = null, 
                            ?string $phase = null, ?string $documentId = null): void
    {
        $sql = "INSERT INTO ocr_progress_events (job_id, event_type, message, current_page, total_pages, phase, document_id) 
                VALUES (:job_id, :event_type, :message, :current_page, :total_pages, :phase, :document_id)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':job_id' => $jobId,
            ':event_type' => $eventType,
            ':message' => $message,
            ':current_page' => $currentPage,
            ':total_pages' => $totalPages,
            ':phase' => $phase,
            ':document_id' => $documentId
        ]);
    }

    /**
     * Obtiene últimos eventos de un job (para SSE)
     */
    public function getRecentEvents(string $jobId, int $limit = 50): array
    {
        $sql = "SELECT * FROM ocr_progress_events 
                WHERE job_id = :job_id 
                ORDER BY timestamp DESC 
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':job_id', $jobId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Obtiene progreso actual para mostrar en stream SSE
     */
    public function getCurrentProgress(string $jobId): array
    {
        $job = $this->getById($jobId);
        
        if (!$job) {
            return [
                'jobId' => $jobId,
                'status' => 'NOT_FOUND',
                'message' => 'Job no encontrado'
            ];
        }

        $percentComplete = $job['total_pages'] > 0 
            ? round(($job['current_page'] / $job['total_pages']) * 100, 0)
            : 0;

        $estimatedRemaining = 0;
        if ($job['current_page'] > 0 && $job['status'] === 'PROCESSING') {
            // Asumir ~2 segundos por página (basado en optimizaciones de PASO 3/4)
            $remainingPages = $job['total_pages'] - $job['current_page'];
            $estimatedRemaining = max(0, $remainingPages * 2);
        }

        return [
            'jobId' => $jobId,
            'status' => $job['status'],
            'currentPage' => (int)$job['current_page'],
            'totalPages' => (int)$job['total_pages'],
            'percentComplete' => $percentComplete,
            'currentPhase' => $job['current_phase'],
            'documentsFound' => (int)$job['documents_found'],
            'estimatedRemainingSeconds' => $estimatedRemaining,
            'errorMessage' => $job['error_message'],
            'startedAt' => $job['started_at'],
            'completedAt' => $job['completed_at'],
            'durationSeconds' => $job['duration_seconds']
        ];
    }

    /**
     * Obtiene jobs activos (para dashboard administrativo)
     */
    public function getActiveJobs(int $limit = 10): array
    {
        $sql = "SELECT * FROM ocr_jobs 
                WHERE status IN ('QUEUED', 'PROCESSING')
                ORDER BY created_at DESC 
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Limpia eventos antiguos (más de 7 días)
     */
    public function cleanOldEvents(int $daysToKeep = 7): int
    {
        $sql = "DELETE FROM ocr_progress_events 
                WHERE timestamp < DATE_SUB(NOW(), INTERVAL :days DAY)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':days', $daysToKeep, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->rowCount();
    }
}
