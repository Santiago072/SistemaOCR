# 📋 Especificación de Requisitos de Software — Sistema OCR

Documento formal de especificación de requisitos funcionales (RF) y no funcionales (RNF) para el **Sistema OCR y Conciliación Documental**.

---

## 1. Requisitos Funcionales (RF)

| ID | Nombre | Descripción | Prioridad |
|---|---|---|---|
| **RF-01** | Carga de Fichas de Formación | El sistema debe permitir registrar el código de ficha, programa y subir simultáneamente el listado Excel y el PDF con cédulas. | Alta |
| **RF-02** | Procesamiento Concurrente OCR | El sistema debe procesar las páginas del PDF mediante inferencia OCR neuronal y extracción de texto con RapidOCR y PyMuPDF. | Alta |
| **RF-03** | Agrupación Frente y Reverso | El sistema debe unificar las dos caras de una misma cédula evitando registros duplicados. | Alta |
| **RF-04** | Cotejo y Conciliación Automática | El motor debe cruzar número de documento y nombres de la cédula contra la planilla oficial de aspirantes. | Alta |
| **RF-05** | Clasificación de Estados | Los registros deben categorizarse en: *Correctas (Conciliadas)*, *Con discrepancia*, *Solo en PDF* y *Solo en Excel*. | Alta |
| **RF-06** | Visor de Cédulas y Edición Manual | El usuario debe poder ver la imagen de la cédula y editar datos erróneos con recálculo dinámico de estado. | Alta |
| **RF-07** | Sincronización con Base de Datos | Los resultados validados deben persistirse en tablas relacionales de MySQL. | Alta |
| **RF-08** | Exportación Avanzada a Excel | El sistema debe generar un archivo `.xlsx` comparativo lado a lado con estilos visuales y auditoría. | Media |

---

## 2. Requisitos No Funcionales (RNF)

* **RNF-01 (Rendimiento)**: El motor OCR debe procesar lotes de documentos con una tasa promedio de 1 a 2 segundos por página.
* **RNF-02 (Seguridad)**: Protección contra vulnerabilidades CSRF en todas las peticiones POST y sentencias preparadas PDO contra inyecciones SQL.
* **RNF-03 (Compatibilidad)**: Funcionamiento en cualquier navegador moderno sin requerir extensiones o plugins externos.
* **RNF-04 (Mantenibilidad)**: Arquitectura desacoplada en PHP MVC y microservicio Python con cobertura de pruebas automatizadas en CI.
