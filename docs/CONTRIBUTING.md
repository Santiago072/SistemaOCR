# 🤝 Guía para Colaboradores — Sistema OCR

¡Gracias por contribuir al proyecto **Sistema OCR & Conciliación Documental**! Sigue estas directrices para mantener la calidad y consistencia del código.

---

## 🛠️ Configuración del Entorno de Desarrollo

1. **Clonar el repositorio:**
   ```bash
   git clone https://github.com/Santiago072/SistemaOCR.git
   cd SistemaOCR
   ```

2. **Instalar dependencias de PHP:**
   ```bash
   composer install
   ```

3. **Instalar dependencias de Python:**
   ```bash
   pip install -r python_ocr/requirements.txt
   ```

---

## 🧪 Ejecución de Pruebas Automatizadas

Antes de realizar un commit o pull request, asegúrate de que todas las pruebas pasen:

* **Pruebas de PHP (PHPUnit):**
  ```bash
  vendor/bin/phpunit
  ```

* **Pruebas de Python (Unittest):**
  ```bash
  python python_ocr/test_ocr_unit.py
  ```

---

## 📝 Convención de Mensajes de Commit

Utilizamos el estándar de **Conventional Commits**:

* `feat:` Nueva funcionalidad para el usuario.
* `fix:` Corrección de un error en el sistema.
* `docs:` Cambios o adiciones a la documentación.
* `refactor:` Refactorización de código sin cambio de comportamiento.
* `test:` Adición o actualización de pruebas unitarias o de integración.

---

## ✅ Checklist para Pull Requests

- [ ] Las pruebas de PHPUnit y Python pasan al 100%.
- [ ] No se introducen credenciales ni secretos en el código fuente.
- [ ] El código sigue las pautas de estilo PSR-12 para PHP y PEP 8 para Python.
- [ ] La documentación en `docs/` o `README.md` ha sido actualizada si corresponde.
