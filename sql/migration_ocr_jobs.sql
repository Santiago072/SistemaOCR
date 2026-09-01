-- ==========================================================
-- Migration: Crear tabla ocr_jobs para monitoreo en tiempo real
-- Fecha: 28 agosto 2026
-- Propósito: Rastrear progreso de procesamiento OCR
-- ==========================================================

-- Tabla principal de jobs OCR
CREATE TABLE IF NOT EXISTS `ocr_jobs` (
    `id` VARCHAR(36) PRIMARY KEY,
    `ficha_id` INT NULL,
    `status` ENUM('QUEUED', 'PROCESSING', 'COMPLETED', 'ERROR', 'CANCELLED') DEFAULT 'QUEUED',
    `total_pages` INT DEFAULT 0,
    `current_page` INT DEFAULT 0,
    `current_phase` VARCHAR(50) DEFAULT '',
    `documents_found` INT DEFAULT 0,
    `error_message` TEXT NULL,
    `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL,
    `duration_seconds` DECIMAL(8,2) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_ficha_id` (`ficha_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de eventos/progreso OCR
CREATE TABLE IF NOT EXISTS `ocr_progress_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `job_id` VARCHAR(36) NOT NULL,
    `event_type` ENUM('PROGRESS', 'PHASE', 'DOCUMENT', 'ERROR', 'WARNING') DEFAULT 'PROGRESS',
    `message` TEXT NULL,
    `current_page` INT NULL,
    `total_pages` INT NULL,
    `phase` VARCHAR(50) NULL,
    `document_id` VARCHAR(30) NULL,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_job_id` (`job_id`),
    KEY `idx_event_type` (`event_type`),
    KEY `idx_timestamp` (`timestamp`),
    CONSTRAINT `fk_progress_job` FOREIGN KEY (`job_id`) REFERENCES `ocr_jobs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indice para búsquedas rápidas de jobs activos
CREATE INDEX `idx_active_jobs` ON `ocr_jobs`(`status`, `created_at`);
