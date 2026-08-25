# Sistema OCR & Conciliación Documental

Sistema automatizado de alto rendimiento para la conciliación y auditoría entre reportes de inscripción en Excel y documentos de identidad colombianos en formato PDF (Cédulas Tradicionales, Cédulas Digitales y Contraseñas de la Registraduría).

---

## 🚀 Características Principales

* **Extracción Híbrida Inteligente (PDF417 + Redes Neuronales OCR)**:
  * Decodificación nativa de alta velocidad de códigos de barras bidimensionales **PDF417**.
  * Reconocimiento óptico de caracteres mediante **RapidOCR (ONNX Runtime)** adaptado con redes neuronales para documentos colombianos.
  * Soporte para orientación multi-ángulo (0°, 90°, 180°, 270°) y filtros contra marcas de agua (*CamScanner*, firmas y sellos).
* **Paralelismo Multinúcleo**:
  * Procesamiento concurrente de páginas PDF con `ProcessPoolExecutor`, logrando procesar decenas de páginas en menos de 50 segundos.
* **Motor de Cruce y Conciliación**:
  * Algoritmos de similitud de texto (Jaro-Winkler y Levenshtein) para contrastar nombres del reporte vs nombres extraídos por OCR.
  * Clasificación automática en: **Conciliado (100%)**, **Diferencia en Nombres**, **Faltante en PDF** o **Sobrante en PDF**.
* **Dashboard Interactivo & Auditoría Visual**:
  * Métricas en tiempo real con cronómetro de alta precisión durante el procesamiento.
  * Visor modal interactivo para inspeccionar la cédula/documento de cada aspirante.
  * Historial de fichas con registro persistente de duración (`⏱ Tiempo OCR`).

---

## 🛠️ Stack Tecnológico

### Backend de Procesamiento e Inteligencia Artificial (Python 3.10+)
* **[RapidOCR](https://github.com/RapidAI/RapidOCR) (`rapidocr-onnxruntime`)**: Inferencia OCR profunda en CPU optimizada con ONNX.
* **[zxing-cpp](https://github.com/zxing-cpp/zxing-cpp)**: Motor C++ nativo de decodificación de códigos de barras PDF417.
* **[PyMuPDF / fitz](https://pymupdf.readthedocs.io/)**: Renderizado y rasterizado ultrarrápido de documentos PDF.
* **[OpenCV](https://opencv.org/) (`opencv-python`) & [NumPy](https://numpy.org/)**: Procesamiento digital de imágenes, filtrado y preprocesamiento.
* **[openpyxl](https://openpyxl.readthedocs.io/) & [xlrd](https://xlrd.readthedocs.io/)**: Parseo de planillas Excel (`.xlsx`, `.xls`).

### Backend Web & Arquitectura (PHP 8.2+)
* **PHP MVC Nativo**: Arquitectura desacoplada sin sobrecarga de frameworks, estructurada en Controladores, Modelos, Servicios y Vistas.
* **PDO MySQL**: Conexión segura mediante Singleton y sentencias preparadas contra inyecciones SQL.
* **Seguridad CSRF/XSS**: Tokens criptográficos automáticos `X-CSRF-TOKEN` y sanitización estricta de entradas.

### Base de Datos
* **MySQL / MariaDB (InnoDB)**: Almacenamiento relacional con codificación `utf8mb4_unicode_ci`.

### Frontend
* **HTML5 Semántico & Vanilla CSS Modular**: Interfaz moderna, diseño responsivo, glassmorphism y paleta de colores HSL.
* **JavaScript ES6+**: Drag & drop de archivos, cronómetro en vivo, filtros de tabla y modales sin librerías externas pesadas.
* **Google Fonts**: Tipografías *Inter* y *Outfit*.

---

## 📂 Estructura del Proyecto

```text
SistemaOCR/
├── app/
│   ├── controllers/      # Controladores MVC (Ficha, Cruce, Home, Api)
│   ├── core/             # Núcleo MVC (Database, Model, Controller, Security, Container)
│   ├── models/           # Modelos de BD (Ficha, AspiranteExcel, DocumentoPdfOcr, Cruce)
│   ├── services/         # Servicios de negocio (MatchingService, ExcelReaderService, OcrPythonBridgeService)
│   └── views/            # Vistas PHP (Dashboard, Carga, Informe, Layouts)
├── config/               # Cargador de variables de entorno (.env)
├── database/             # Scripts SQL de estructura de base de datos
├── public/               # Recursos estáticos (CSS modular, JavaScript Vanilla, Assets)
│   ├── css/              # variables.css, layout.css, components.css, cruce.css, dropzone.css
│   └── js/               # app.js, dropzone-uploader.js, cruce-dashboard.js
├── python_ocr/           # Microservicio y CLI de extracción OCR y PDF417
│   ├── extractor.py      # Orquestador multinúcleo de procesamiento de PDFs
│   ├── pdf417_decoder.py # Decodificador de código de barras colombiano
│   ├── text_ocr.py       # Motor RapidOCR y reglas de cédulas colombianas
│   ├── excel_parser.py   # Parser de reportes Excel
│   └── requirements.txt  # Dependencias Python
├── uploads/              # Almacenamiento seguro de archivos y recortes generados
├── .env.example          # Plantilla de configuración de entorno
├── index.php             # Front Controller principal
└── README.md             # Documentación del sistema
```

---

## ⚙️ Requisitos e Instalación

### 1. Requisitos Previos
* **XAMPP / Servidor Web** con PHP 8.2 o superior y MySQL/MariaDB.
* **Python 3.10+** instalado y configurado en el `PATH` del sistema.

### 2. Configuración de Base de Datos
1. Crear la base de datos en MySQL:
   ```sql
   CREATE DATABASE sistema_ocr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Importar la estructura desde `database/schema.sql`.

### 3. Configuración de Variables de Entorno
Copiar el archivo `.env.example` a `.env` y configurar las credenciales:
```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=sistema_ocr
DB_USER=root
DB_PASS=
PYTHON_CMD=python
```

### 4. Instalación de Dependencias Python
Ejecutar en la terminal dentro de la raíz del proyecto:
```bash
pip install -r python_ocr/requirements.txt
```

---

## 🖥️ Uso del Sistema

1. Iniciar Apache y MySQL en XAMPP.
2. Ingresar a `http://localhost/SistemaOCR/`.
3. Hacer clic en **"Nueva Ficha (Excel + PDF)"**.
4. Subir el archivo **Excel** con el listado de participantes y el **PDF** con los documentos de identidad.
5. El sistema procesará en paralelo las páginas y redirigirá automáticamente a la **Matriz de Cruce y Validación Documental**, permitiendo auditar y exportar los resultados.
