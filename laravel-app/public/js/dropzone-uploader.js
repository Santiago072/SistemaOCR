/**
 * Manejador de la Zona de Carga Drag & Drop
 * Usa submit nativo del formulario para evitar restricciones de cabeceras HTTP en Apache/XAMPP
 */
document.addEventListener('DOMContentLoaded', () => {
    const uploadForm = document.getElementById('uploadForm');
    const inputExcel = document.getElementById('archivo_excel');
    const inputPdf   = document.getElementById('archivo_pdf');
    const excelInfo  = document.getElementById('excelFileInfo');
    const pdfInfo    = document.getElementById('pdfFileInfo');
    const btnSubmit  = document.getElementById('btnSubmitUpload');
    const processingIndicator = document.getElementById('processingIndicator');

    function updateFileInfo(input, displayEl) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
            displayEl.innerHTML = '✓ ' + file.name + ' (' + sizeMb + ' MB)';
            displayEl.style.color = '#15803d';
        }
    }

    inputExcel?.addEventListener('change', () => updateFileInfo(inputExcel, excelInfo));
    inputPdf?.addEventListener('change', () => updateFileInfo(inputPdf, pdfInfo));

    // Drag and Drop highlight
    ['dropzoneExcel', 'dropzonePdf'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        ['dragenter', 'dragover'].forEach(ev => {
            el.addEventListener(ev, (e) => { e.preventDefault(); el.classList.add('dragover'); });
        });
        ['dragleave', 'drop'].forEach(ev => {
            el.addEventListener(ev, (e) => { e.preventDefault(); el.classList.remove('dragover'); });
        });
    });

    // Envío del Formulario: submit con barra de avance visual
    uploadForm?.addEventListener('submit', (e) => {
        if (!inputExcel.files.length || !inputPdf.files.length) {
            e.preventDefault();
            alert('Por favor selecciona ambos archivos: el Excel de inscripciones y el PDF de cédulas.');
            return;
        }

        // Asegurar que el input csrf_token tenga valor actualizado desde el meta tag si está vacío
        let tokenInput = uploadForm.querySelector('input[name="csrf_token"]');
        const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (tokenInput && metaToken && !tokenInput.value) {
            tokenInput.value = metaToken;
        }

        // Mostrar indicador de procesamiento y deshabilitar botón
        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.style.opacity = '0.5';
        }

        const progressBarFill = document.getElementById('progressBarFill');
        const processingStep = document.getElementById('processingStep');

        if (processingIndicator) {
            processingIndicator.style.display = 'flex';
        }

        // Animación fluida de la línea de avance mientras el backend procesa
        let progress = 5;
        if (progressBarFill) progressBarFill.style.width = progress + '%';

        const interval = setInterval(() => {
            if (progress < 90) {
                progress += Math.floor(Math.random() * 8) + 2;
                if (progress > 90) progress = 90;
                if (progressBarFill) progressBarFill.style.width = progress + '%';

                if (progress > 15 && progress <= 40 && processingStep) {
                    processingStep.innerText = 'Extrayendo lista de inscritos de Excel...';
                } else if (progress > 40 && progress <= 70 && processingStep) {
                    processingStep.innerText = 'Decodificando códigos de barras y OCR PaddleOCR...';
                } else if (progress > 70 && processingStep) {
                    processingStep.innerText = 'Ejecutando matriz de cruce y conciliación...';
                }
            }
        }, 1200);

        // Permitir el submit nativo del formulario
    });
});
