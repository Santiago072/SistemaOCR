# Sistema OCR & Conciliación Documental Inteligente

Sistema integral de alto rendimiento para la conciliación, cotejo automatizado y auditoría documental entre listados de inscripción en Excel y documentos de identidad colombianos en formato PDF (Cédulas Tradicionales, Cédulas Digitales con zona MRZ, Reversos y Contraseñas de la Registraduría).

---

## 🚀 Características Principales

* **Extracción Neuronal OCR y Soporte Integral de Cédulas Colombianas**:
  * Reconocimiento óptico de caracteres mediante **RapidOCR (ONNX Runtime)** adaptado para documentos de identidad nacionales.
  * Detección y decodificación de **zonas legibles por máquina (MRZ)** de cédulas digitales colombianas.
  * Agrupación inteligente de caras (Frente y Reverso) por coincidencia de apellidos, fecha de nacimiento o cercanía de dígitos OCR.
  * Manejo de documentos con orientación variable (0°, 90°, 180°, 270°) y micro-texto.
* **Procesamiento Asíncrono en Tiempo Real**:
  * Microservicio local de alta eficiencia en Flask/Waitress que procesa en paralelo las páginas con PyMuPDF.
  * Transmisión de progreso en vivo con cronómetro de lectura e inferencia.
* **Motor de Cruce, Cotejo y Conciliación**:
  * Algoritmos de similitud de texto para contrastar nombres y números del listado oficial contra lo leído por OCR.
  * Clasificación automática en:
    * **Correctas (Coinciden)**: Documento y nombre conciliados al 100%.
    * **Con discrepancia**: Difieren en número de cédula, tipo de documento (TI/CC) o nombres/apellidos.
    * **Solo en PDF**: Cédulas presentes en el PDF que no están en el listado de Excel.
    * **Solo en Excel (Faltantes)**: Aspirantes registrados que no adjuntaron su cédula en el PDF.
* **Visor Interactivo y Edición Manual en Vivo**:
  * Visor modal de alta resolución para inspeccionar el recorte de la cédula de cada aspirante.
  * Corrección manual en tiempo real con recálculo automático de métricas y estados.
* **Exportación Avanzada a Excel (.xlsx)**:
  * Generación de reportes corporativos con comparativa lado a lado (Datos PDF vs Datos Excel), resaltado visual de estados, pestañas separadas para casos faltantes y anchos de columna auto-ajustados.

---

## 🛠️ Stack Tecnológico

### Backend de Procesamiento e Inteligencia Artificial (Python 3.10+)
* **[RapidOCR](https://github.com/RapidAI/RapidOCR) (`rapidocr-onnxruntime`)**: Inferencia OCR profunda en CPU optimizada con ONNX.
* **[PyMuPDF / fitz](https://pymupdf.readthedocs.io/)**: Renderizado y rasterizado ultrarrápido de documentos PDF.
* **[OpenCV](https://opencv.org/) (`opencv-python`) & [NumPy](https://numpy.org/)**: Procesamiento digital de imágenes, recorte adaptativo y filtros.
* **[openpyxl](https://openpyxl.readthedocs.io/) & [xlrd](https://xlrd.readthedocs.io/)**: Generación y lectura de planillas Excel (`.xlsx`, `.xls`).
* **[Flask](https://flask.palletsprojects.com/) & [Waitress](https://docs.pylonsproject.org/projects/waitress/en/latest/)**: Microservicio web concurrente de alta disponibilidad.

### Backend Web & Arquitectura (PHP 8.2+)
* **PHP MVC Nativo**: Arquitectura desacoplada estructurada en Controladores, Modelos, Servicios y Vistas.
* **PDO MySQL**: Conexión segura con sentencias preparadas contra inyecciones SQL.
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
│   ├── controllers/      # Controladores MVC (FichaController, CruceController, HomeController, ApiController)
│   ├── core/             # Núcleo MVC (Database, Model, Controller, Security, Container)
│   ├── models/           # Modelos de BD (Ficha, AspiranteExcel, DocumentoPdfOcr, Cruce, OcrJob)
│   ├── services/         # Servicios de negocio (ExcelReaderService, OcrPythonBridgeService)
│   └── views/            # Vistas PHP (Dashboard, Carga, Informe de Cruce, Layouts)
├── config/               # Cargador de variables de entorno (.env)
├── database/             # Scripts SQL de estructura de base de datos
├── public/               # Recursos estáticos (CSS modular, JavaScript Vanilla, Assets)
│   ├── css/              # variables.css, layout.css, components.css, cruce.css, dropzone.css
│   └── js/               # app.js, dropzone-uploader.js, cruce-dashboard.js
├── python_ocr/           # Microservicio y motor de extracción OCR y cotejo
│   ├── ocr.py            # Rasterizado PyMuPDF y motor RapidOCR
│   ├── campos.py         # Extracción y validación de campos colombianos y zona MRZ
│   ├── cotejo.py         # Agrupación de páginas (frente/reverso) y cotejo contra Excel
│   ├── exportar.py       # Generador de reportes comparativos Excel (.xlsx) y CSV
│   ├── servidor.py       # API Flask/Waitress concurrente con endpoints de procesamiento y exportación
│   ├── excel_parser.py   # Parser de reportes Excel
│   └── requirements.txt  # Dependencias Python
├── sql/                  # Scripts de migración de base de datos
├── uploads/              # Almacenamiento de archivos y recortes de cédulas generados
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
2. Importar la estructura desde `database/schema.sql` y las migraciones en `sql/`.

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
5. El sistema procesará en vivo las páginas y presentará los resultados en la **Matriz de Cruce y Validación Documental**.
6. Puedes auditar, inspeccionar la cédula de cualquier participante con el botón **"🔍 Ver Cédula"**, editar datos a mano, **Guardar / Sincronizar en BD** o descargar el reporte oficial con el botón **"📊 Exportar a Excel (.xlsx)"**.
