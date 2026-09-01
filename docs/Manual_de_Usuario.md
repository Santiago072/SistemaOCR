# Manual de Usuario — Sistema OCR & Conciliación

Guía visual y operativa para el uso del **Sistema OCR y Conciliación Documental**.

---

## 1. Ingreso al Sistema y Dashboard

1. Abra su navegador web e ingrese a la dirección del sistema: `http://localhost/SistemaOCR/`.
2. En la pantalla principal verá el listado histórico de fichas cargadas, su estado de procesamiento (`PENDIENTE`, `EN_PROCESO`, `CRUCE_COMPLETADO`, `FINALIZADA`), el total de inscritos y el tiempo total de lectura OCR.

---

## 2. Cargar una Nueva Ficha

1. Haga clic en el botón superior **"+ Nueva Ficha"**.
2. Complete los datos básicos:
   - **Código de Ficha**: Identificador numérico de la ficha (Ej: `3591229`).
   - **Programa de Formación**: Nombre del curso o programa académico.
3. Adjunte los archivos requeridos:
   - **Listado en Excel**: Planilla de aspirantes inscritos (`.xlsx` o `.xls`).
   - **PDF de Documentos**: Archivo PDF que contiene las imágenes de las cédulas o tarjetas de identidad.
4. Haga clic en **"Procesar y Realizar Cruce"**.

---

## 3. Matriz de Cruce y Validación en Vivo

Durante el procesamiento, el sistema mostrará una barra de avance y un cronómetro en tiempo real. Al finalizar, se presentan las métricas de resumen:

* 🟢 **Correctas (Coinciden)**: Aspirantes cuyos datos del PDF concuerdan con el listado de Excel.
* 🟡 **Con discrepancia**: Casos donde difiere el número, tipo de documento o nombres/apellidos.
* 🔴 **Solo en PDF**: Cédulas leídas en el PDF que no se encuentran en el listado de Excel.
* ⚪ **Solo en Excel**: Personas del listado que no adjuntaron su documento de identidad en el PDF.

---

## 4. Visor Interactivo y Edición Manual

1. En la tabla de resultados, haga clic en el botón **"🔍 Ver Cédula"** de cualquier registro.
2. Se abrirá un modal con el recorte ampliado de la cédula del aspirante y los campos extraídos:
   - Tipo de Documento.
   - Número de Identificación.
   - Nombres y Apellidos.
3. Si la cédula presentaba alguna discrepancia o lectura incompleta, corríjala directamente en los campos y haga clic en **"💾 Aplicar Cambios"**.
4. El sistema actualizará el estado de la fila y los contadores automáticamente.

---

## 5. Exportar Reporte a Excel (.xlsx)

1. En la barra superior de acciones, haga clic en **"📊 Exportar a Excel (.xlsx)"**.
2. El sistema descargará un archivo estructurado que contiene:
   - **Hoja 1 (`Cruce PDF vs Excel`)**: Comparativa detallada lado a lado con colores de auditoría, fechas de nacimiento, RH y edades calculadas.
   - **Hoja 2 (`Solo en Excel`)**: Listado de aspirantes pendientes por adjuntar documento.
