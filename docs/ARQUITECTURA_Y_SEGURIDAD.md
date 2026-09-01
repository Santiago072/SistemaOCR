# Arquitectura y Seguridad del Sistema OCR & Conciliación

Este documento describe en profundidad los patrones de diseño, componentes arquitectónicos, flujos de procesamiento asíncrono y medidas de seguridad implementadas en **Sistema OCR**.

---

## 1. Diagrama de Arquitectura

```
┌───────────────────────────────────────────────────────────────┐
│                       Cliente Web (Navegador)                 │
│         - Interfaz Responsiva (CSS Modular / ES6+)            │
│         - Polling Asíncrono de Avance (SSE / Fetch)           │
│         - Visor Modal de Cédulas y Editor en Vivo             │
└───────────────────────────────┬───────────────────────────────┘
                                │ HTTP / JSON
                                ▼
┌───────────────────────────────────────────────────────────────┐
│               Servidor Web / Backend PHP (MVC)                │
│  - Front Controller (index.php) + Router                      │
│  - Inyección de Dependencias (Container)                      │
│  - Controladores: FichaController, CruceController, Api...    │
│  - Modelos PDO: Ficha, Cruce, AspiranteExcel, OcrJob          │
│  - Seguridad: CSRF Tokens, XSS Sanitizer, Prepared Stmts      │
└──────────────┬────────────────────────────────┬───────────────┘
               │                                │
    cURL / IPC │                                │ SQL (PDO)
               ▼                                ▼
┌──────────────────────────────┐ ┌──────────────────────────────┐
│ Microservicio Python OCR/IA  │ │    Base de Datos MySQL       │
│ - Servidor Flask / Waitress  │ │ - fichas                     │
│ - PyMuPDF (Renderizado)      │ │ - aspirantes_excel           │
│ - RapidOCR (ONNX Runtime)    │ │ - documentos_pdf_ocr         │
│ - Decodificación MRZ         │ │ - cruce_conciliacion         │
│ - Motor de Cotejo Inteligente│ │ - ocr_jobs (cola async)      │
│ - Exportador OpenPyXL        │ └──────────────────────────────┘
└──────────────────────────────┘
```

---

## 2. Flujo de Extracción y Conciliación

1. **Recepción de Archivos**:
   - Se carga el archivo de **Listado Excel** (`.xlsx`, `.xls`, `.csv`) y el archivo **PDF de Cédulas**.
   - El sistema almacena los archivos en la carpeta protegida `uploads/fichas/` y registra la ficha en la base de datos con estado `PENDIENTE`.

2. **Extracción y Procesamiento Paralelo**:
   - PHP delega el procesamiento al microservicio local de Python en `http://127.0.0.1:5005/api/leer`.
   - Python rasteriza cada página a alta resolución (DPI adaptativo) mediante PyMuPDF.
   - Ejecuta inferencia OCR neuronal con **RapidOCR** extrayendo texto, orientaciones y líneas de código de barras MRZ.

3. **Agrupación Inteligente de Caras (Frente / Reverso)**:
   - Las páginas correspondientes a una misma persona (frente y reverso de la cédula) se unifican automáticamente buscando continuidad en número de documento, coincidencia de apellidos o fecha de nacimiento.
   - Se evita la duplicación de aspirantes o el conteo erróneo de páginas.

4. **Motor de Cotejo contra Listado Oficial**:
   - Se compara cada cédula leída contra la referencia de aspirantes del Excel.
   - Si coincide número y nombres -> **Correcto** (verde).
   - Si existe una diferencia en nombres, número o tipo -> **Con discrepancia** (amarillo).
   - Si no está en el Excel -> **Solo en PDF** (rojo).
   - Si está en el Excel pero no adjuntó cédula -> **Solo en Excel** (gris).

5. **Auditoría, Edición en Vivo y Exportación**:
   - El usuario puede corregir datos directamente en el visor de documentos, recalculándose los estados de inmediato.
   - Los datos se sincronizan con MySQL y se puede descargar el informe formal en formato Excel (`.xlsx`) con comparativa lado a lado.

---

## 3. Políticas de Seguridad Implementadas

* **Protección contra Falsificación de Petición en Sitios Cruzados (CSRF)**:
  - Generación de tokens criptográficos `X-CSRF-TOKEN` validados en cada petición mutativa (`POST`, `PUT`, `DELETE`).
* **Protección contra Inyecciones SQL**:
  - 100% de las consultas a base de datos utilizan `PDO Prepared Statements` con parámetros fuertemente tipados.
* **Sanitización y Prevención de XSS**:
  - Sanitización estricta de cadenas antes de renderizar en el DOM mediante `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
* **Aislamiento de Archivos**:
  - Los archivos subidos y recortes temporales se almacenan fuera de la raíz pública accesible directamente sin autenticación.
