/**
 * Manejador de la Zona de Carga Drag & Drop para Laravel
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

    uploadForm?.addEventListener('submit', (e) => {
        if (!inputExcel.files.length || !inputPdf.files.length) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Archivos requeridos',
                text: 'Por favor selecciona ambos archivos: el Excel de inscripciones y el PDF de cédulas.'
            });
            return;
        }

        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.style.opacity = '0.5';
        }

        const progressBarFill = document.getElementById('progressBarFill');
        const processingStep = document.getElementById('processingStep');

        if (processingIndicator) {
            processingIndicator.style.display = 'flex';
        }

        let progress = 5;
        if (progressBarFill) progressBarFill.style.width = progress + '%';

        const interval = setInterval(() => {
            if (progress < 92) {
                progress += Math.floor(Math.random() * 8) + 2;
                if (progress > 92) progress = 92;
                if (progressBarFill) progressBarFill.style.width = progress + '%';

                if (progress > 15 && progress <= 40 && processingStep) {
                    processingStep.innerText = 'Extrayendo lista de inscritos de Excel...';
                } else if (progress > 40 && progress <= 75 && processingStep) {
                    processingStep.innerText = 'Consultando microservicio FastAPI para decodificación OCR...';
                } else if (progress > 75 && processingStep) {
                    processingStep.innerText = 'Ejecutando matriz de cruce y conciliación...';
                }
            }
        }, 1000);
    });
});
