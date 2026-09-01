-- ==========================================================
-- Migration PASO 6: Deduplicación de frente+reverso
-- Fecha: 28 agosto 2026
-- Propósito: Agregar campos para marcar duplicados y fusiones
-- ==========================================================

-- Agregar columnas para tracking de deduplicación
ALTER TABLE `documentos_pdf_ocr` ADD COLUMN (
    `metodo_extraccion_original` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Métodooriginal antes de marcar duplicado',
    `confianza_score_original` DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'Score original antes de marcar duplicado'
) AFTER `confianza_score`;

-- Comentario en metodo_extraccion para documenting
ALTER TABLE `documentos_pdf_ocr` CHANGE COLUMN `metodo_extraccion` `metodo_extraccion` 
ENUM('PDF417', 'OCR_TESSERACT', 'MANUAL', 'FALLIDO', 'PDF417+OCR_COMBINED', 'PDF417_DUPLICADO', 'OCR_TESSERACT_DUPLICADO', 'MANUAL_DUPLICADO', 'FALLIDO_DUPLICADO') 
DEFAULT 'PDF417' 
COMMENT 'Método de extracción; _DUPLICADO marca documentos ya fusionados';

-- Índice para búsquedas rápidas de documentos duplicados
CREATE INDEX `idx_duplicados` ON `documentos_pdf_ocr`(
    `ficha_id`, 
    `metodo_extraccion`
) WHERE `metodo_extraccion` NOT LIKE '%_DUPLICADO';

-- Índice para búsquedas por número (ya existe, pero asegurar)
CREATE INDEX IF NOT EXISTS `idx_numero_ficha` ON `documentos_pdf_ocr`(
    `ficha_id`,
    `numero_documento`
);
