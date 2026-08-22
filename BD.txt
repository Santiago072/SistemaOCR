-- ==========================================================
-- Base de Datos para Sistema OCR y Conciliacion Documental
-- Nombre de BD: sistema_ocr
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `sistema_ocr` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sistema_ocr`;

-- 1. Tabla de Fichas de Formacion
CREATE TABLE IF NOT EXISTS `fichas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `codigo_ficha` VARCHAR(50) NOT NULL UNIQUE,
    `programa_formacion` VARCHAR(255) NOT NULL,
    `total_inscritos` INT DEFAULT 0,
    `archivo_excel_nombre` VARCHAR(255) NULL,
    `archivo_pdf_nombre` VARCHAR(255) NULL,
    `estado` ENUM('CARGADA', 'PROCESANDO_OCR', 'CRUCE_COMPLETADO', 'IMPORTADA') DEFAULT 'CARGADA',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla de Aspirantes cargados desde el Excel
CREATE TABLE IF NOT EXISTS `aspirantes_excel` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ficha_id` INT NOT NULL,
    `tipo_documento` VARCHAR(10) NOT NULL,
    `numero_documento` VARCHAR(30) NOT NULL,
    `nombre_completo` VARCHAR(255) NOT NULL,
    `estado_inscripcion` VARCHAR(100) DEFAULT 'Preinscrito',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_aspirantes_ficha` FOREIGN KEY (`ficha_id`) REFERENCES `fichas`(`id`) ON DELETE CASCADE,
    INDEX `idx_aspirante_doc` (`numero_documento`),
    INDEX `idx_aspirante_ficha` (`ficha_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabla de Documentos extraidos del PDF mediante OCR / PDF417
CREATE TABLE IF NOT EXISTS `documentos_pdf_ocr` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ficha_id` INT NOT NULL,
    `numero_pagina` INT NOT NULL,
    `tipo_documento` VARCHAR(10) DEFAULT 'CC',
    `numero_documento` VARCHAR(30) NULL,
    `primer_apellido` VARCHAR(100) NULL,
    `segundo_apellido` VARCHAR(100) NULL,
    `primer_nombre` VARCHAR(100) NULL,
    `segundo_nombre` VARCHAR(100) NULL,
    `nombre_completo_ocr` VARCHAR(255) NULL,
    `genero` VARCHAR(10) NULL,
    `fecha_nacimiento` VARCHAR(30) NULL,
    `rh` VARCHAR(5) NULL,
    `metodo_extraccion` ENUM('PDF417', 'OCR_TESSERACT', 'MANUAL', 'FALLIDO') DEFAULT 'PDF417',
    `confianza_score` DECIMAL(5,2) DEFAULT 100.00,
    `ruta_imagen_recorte` VARCHAR(255) NULL,
    `raw_data_json` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_documentos_ficha` FOREIGN KEY (`ficha_id`) REFERENCES `fichas`(`id`) ON DELETE CASCADE,
    INDEX `idx_ocr_doc` (`numero_documento`),
    INDEX `idx_ocr_ficha` (`ficha_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabla del Informe de Cruce y Conciliacion
CREATE TABLE IF NOT EXISTS `cruce_conciliacion` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ficha_id` INT NOT NULL,
    `aspirante_excel_id` INT NULL,
    `documento_pdf_id` INT NULL,
    `estado_cruce` ENUM(
        'CONCILIADO', 
        'DIFERENCIA_NOMBRE', 
        'FALTANTE_PDF', 
        'SOBRANTE_PDF', 
        'ILEGIBLE'
    ) NOT NULL,
    `similitud_nombres_porcentaje` DECIMAL(5,2) DEFAULT 0.00,
    `observaciones` TEXT NULL,
    `validado_manualmente` TINYINT(1) DEFAULT 0,
    `fecha_validacion` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cruce_ficha` FOREIGN KEY (`ficha_id`) REFERENCES `fichas`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cruce_aspirante` FOREIGN KEY (`aspirante_excel_id`) REFERENCES `aspirantes_excel`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_cruce_documento` FOREIGN KEY (`documento_pdf_id`) REFERENCES `documentos_pdf_ocr`(`id`) ON DELETE SET NULL,
    INDEX `idx_cruce_estado` (`estado_cruce`),
    INDEX `idx_cruce_ficha` (`ficha_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabla Final de Participantes Importados / Aprobados
CREATE TABLE IF NOT EXISTS `participantes_finales` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ficha_id` INT NOT NULL,
    `tipo_documento` VARCHAR(10) NOT NULL,
    `numero_documento` VARCHAR(30) NOT NULL,
    `nombres` VARCHAR(150) NOT NULL,
    `apellidos` VARCHAR(150) NOT NULL,
    `nombre_completo` VARCHAR(255) NOT NULL,
    `genero` VARCHAR(10) NULL,
    `fecha_nacimiento` VARCHAR(30) NULL,
    `rh` VARCHAR(5) NULL,
    `estado_inscripcion` VARCHAR(100) DEFAULT 'Matriculado / Validadado',
    `origen_validacion` ENUM('AUTOMATICO_OCR', 'MANUAL_SUPERVISOR') DEFAULT 'AUTOMATICO_OCR',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_participantes_ficha` FOREIGN KEY (`ficha_id`) REFERENCES `fichas`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_ficha_documento` (`ficha_id`, `numero_documento`),
    INDEX `idx_participante_doc` (`numero_documento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
