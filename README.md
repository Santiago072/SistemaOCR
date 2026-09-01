# 🔍 Sistema OCR — Conciliación y Auditoría Documental Inteligente

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php)](https://www.php.net/)
[![Python](https://img.shields.io/badge/Python-3.10%2B-3776AB?style=flat-square&logo=python)](https://www.python.org/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat-square&logo=mysql)](https://www.mysql.com/)
[![PHPUnit](https://img.shields.io/badge/Tests-PHPUnit%2010-37b24d?style=flat-square&logo=php)](phpunit.xml)
[![CI](https://img.shields.io/badge/CI-GitHub%20Actions-2088FF?style=flat-square&logo=githubactions)](.github/workflows/ci.yml)
[![Licencia](https://img.shields.io/badge/Licencia-Comercial%20Propietaria-e03c3c?style=flat-square)](LICENSE)
[![Versión](https://img.shields.io/badge/Versión-v2.1.0-10757e?style=flat-square)](docs/CHANGELOG.md)

Bienvenido al **Sistema OCR & Conciliación Documental**. Es una solución integral y automatizada de alto rendimiento desarrollada para auditar, procesar y cotejar planillas de inscripción en Excel contra documentos de identidad colombianos en formato PDF (Cédulas Tradicionales, Cédulas Digitales con zona MRZ, Reversos y Contraseñas de la Registraduría). Cuenta con extracción neuronal con RapidOCR, visor interactivo con edición en tiempo real, sincronización con base de datos MySQL y exportación avanzada a reportes corporativos en Excel (.xlsx).

---

| Documento | Descripción |
|-----------|-------------|
| 🏗️ [Arquitectura y Seguridad](docs/ARQUITECTURA_Y_SEGURIDAD.md) | Arquitectura profunda, esquemas de BD, microservicio OCR y medidas de seguridad |
| 👤 [Manual de Usuario](docs/Manual_de_Usuario.md) | Guía de uso de la aplicación para usuarios finales y auditores |
| 📜 [Registro de Cambios](docs/CHANGELOG.md) | Historial de versiones y modificaciones del sistema (v2.1.0) |
| 📋 [Plan de Implementación](docs/PLAN_DE_IMPLEMENTACION.md) | Fases del proyecto, stack tecnológico y arquitectura empresarial |
| 📋 [Especificación de Requisitos](docs/ESPECIFICACION_REQUISITOS.md) | Requisitos funcionales (RF), RNF, motor de cotejo y modelo de datos |
| 🤝 [Guía para Colaboradores](docs/CONTRIBUTING.md) | Configuración local, pruebas unitarias con PHPUnit y Python, checklist de PR |
| ⚖️ [Licencia Comercial](LICENSE) | Términos legales de propiedad intelectual, uso comercial y mantenimiento |

---

## 🛠️ Tecnologías e Infraestructura Utilizadas

* **Backend Web:** PHP 8.1+ / 8.2 (Arquitectura MVC desacoplada sin sobrecarga de frameworks).
* **Persistencia:** `PDO` (PHP Data Objects) con sentencias preparadas y parámetros tipados.
* **Motor IA & Extracción OCR:** Python 3.10+, RapidOCR (`rapidocr-onnxruntime`), PyMuPDF (`fitz`), OpenCV y NumPy.
* **Microservicio Concurrente:** Flask & Waitress (Servidor WSGI de producción multihilo).
* **Generación de Reportes:** `openpyxl` para generación de Excel `.xlsx` corporativo comparativo lado a lado.
* **Base de Datos:** MySQL 8.0+ / MariaDB 10.4+ (`utf8mb4_unicode_ci`).
* **Pruebas Automatizadas:** [PHPUnit 10](https://phpunit.de/) para PHP y `unittest` para Python.
* **Integración Continua:** GitHub Actions (`.github/workflows/ci.yml`).
* **Frontend:** HTML5 semántico, CSS3 Vanilla modularizado, Vanilla JavaScript ES6+ y Google Fonts (*Inter* / *Outfit*).

---

## 🏛️ Arquitectura del Sistema

```text
index.php (Front Controller & Router con Security Headers)
 ├── HomeController         → Dashboard principal con listado histórico de fichas
 ├── FichaController        → Carga de archivos (Excel + PDF) y orquestación de procesamiento
 ├── CruceController        → Visualización de la matriz de cruce, visor de cédulas y exportación
 └── ApiController          → Endpoints asíncronos para consulta de estado y avance en tiempo real

Microservicio Python (http://127.0.0.1:5005)
 ├── servidor.py            → API REST en Flask/Waitress
 ├── ocr.py                 → Inferencia con RapidOCR y rasterizado PyMuPDF
 ├── campos.py              → Normalización fonética y decodificación MRZ
 ├── cotejo.py              → Agrupación frente/reverso y cotejo contra Excel
 └── exportar.py            → Generador de reportes comparativos en Excel (.xlsx)
```

---

## ⚡ Instalación y Puesta en Marcha

### 1. Clonar el repositorio
```bash
git clone https://github.com/Santiago072/SistemaOCR.git
cd SistemaOCR
```

### 2. Instalar dependencias
```bash
# Dependencias de PHP
composer install

# Dependencias de Python
pip install -r python_ocr/requirements.txt
```

### 3. Configurar variables de entorno (`.env`)
```bash
cp .env.example .env
```
Ajustar las credenciales locales de la base de datos (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).

### 4. Importar base de datos
Importar `database/schema.sql` y las migraciones en `sql/` en MySQL / phpMyAdmin.

### 5. Iniciar microservicio Python
```bash
python python_ocr/servidor.py
```

### 6. Ejecutar pruebas unitarias
```bash
# Pruebas de PHP
vendor/bin/phpunit

# Pruebas de Python
python python_ocr/test_ocr_unit.py
```
