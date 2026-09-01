# 📜 Registro de Cambios (Changelog) — Sistema OCR

Todos los cambios notables en este proyecto están documentados en este archivo.

El formato se basa en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/) y este proyecto se adhiere a [Semantic Versioning](https://semver.org/lang/es/).

---

## [2.1.0] - 2026-09-01

### Agregado
- 📊 **Exportación Avanzada a Excel (.xlsx)**: Generador corporativo con comparativa lado a lado (Datos PDF vs Datos Excel), resaltado condicional de estados y pestañas especializadas.
- ⚡ **Integración Continua con GitHub Actions (`.github/workflows/ci.yml`)**: Validación automática de pruebas unitarias en PHP 8.2 y motor Python 3.10.
- 🧪 **Suite de Pruebas Unitarias Automatizadas**:
  - PHPUnit para seguridad (CSRF, sanitización XSS) e inyección de dependencias (`Container`).
  - Python unittest para cálculo de edad, normalización de textos colombianos y similitud de nombres.
- 📑 **Suite Documental en `docs/`**: Arquitectura y Seguridad, Manual de Usuario, Plan de Implementación, Especificación de Requisitos, Guía para Colaboradores y Registro de Cambios.

### Optimizado
- 🏎️ **Agrupación Inteligente de Caras (Frente / Reverso)**: Unificación automática por documento/apellidos sin generar duplicados.
- ✏️ **Cotejo y Edición Manual en Vivo**: Detección estricta de alteraciones en nombres y apellidos para recálculo instantáneo de estados en el visor.

---

## [2.0.0] - 2026-08-30

### Agregado
- 🧠 **Motor Neuronal RapidOCR (ONNX Runtime)**: Reemplazo de Tesseract por RapidOCR para inferencia en CPU optimizada.
- 🚀 **Microservicio Concurrente Flask/Waitress**: API local de alta disponibilidad para procesamiento de lotes PDF.
- 📑 **Soporte para Cédulas Digitales con Zona MRZ**: Decodificación de líneas MRZ colombianas en tarjetas y cédulas nuevas.

---

## [1.0.0] - 2026-08-15

### Agregado
- 🚀 Versión inicial del Sistema OCR y Conciliación Documental en PHP MVC nativo y MySQL.
- 📂 Subida de fichas, carga de archivos Excel y visualización de resultados básicos.
