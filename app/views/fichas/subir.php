<div class="upload-container">
    <div class="page-banner">
        <div class="banner-content">
            <h2>Cargar Ficha y Documentación</h2>
            <p>Sube el archivo Excel de reporte de inscripciones y el PDF con las cédulas de los participantes para procesar la conciliación.</p>
        </div>
    </div>

    <form id="uploadForm" class="upload-form" action="<?= $base ?>index.php?ruta=ficha/procesar" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <div class="upload-grid">
            <!-- Zona 1: Archivo Excel -->
            <div class="card upload-card" id="dropzoneExcel">
                <div class="card-header">
                    <div class="card-header-icon excel-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="8" y1="13" x2="16" y2="13"></line>
                            <line x1="8" y1="17" x2="16" y2="17"></line>
                            <line x1="10" y1="9" x2="8" y2="9"></line>
                        </svg>
                    </div>
                    <h3>1. Reporte de Inscripciones (Excel)</h3>
                </div>
                <div class="card-body dropzone-body">
                    <input type="file" id="archivo_excel" name="archivo_excel" accept=".xlsx, .xls" class="file-input" required>
                    <div class="dropzone-content">
                        <div class="drop-icon">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                        </div>
                        <p class="drop-text">Arrastra el archivo Excel aquí o <span class="browse-link">examinar</span></p>
                        <span class="file-format-hint">Formatos: .xlsx, .xls (Estructura SENA)</span>
                        <div class="selected-file-info" id="excelFileInfo"></div>
                    </div>
                </div>
            </div>

            <!-- Zona 2: Archivo PDF Documentación -->
            <div class="card upload-card" id="dropzonePdf">
                <div class="card-header">
                    <div class="card-header-icon pdf-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                        </svg>
                    </div>
                    <h3>2. Documentos de Identidad (PDF)</h3>
                </div>
                <div class="card-body dropzone-body">
                    <input type="file" id="archivo_pdf" name="archivo_pdf" accept=".pdf" class="file-input" required>
                    <div class="dropzone-content">
                        <div class="drop-icon">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                        </div>
                        <p class="drop-text">Arrastra el archivo PDF aquí o <span class="browse-link">examinar</span></p>
                        <span class="file-format-hint">Formato: .pdf (Cédulas frente/dorso por página)</span>
                        <div class="selected-file-info" id="pdfFileInfo"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Barra de Progreso y Acciones -->
        <div class="upload-actions-panel">
            <div id="processingIndicator" class="processing-indicator" style="display: none;">
                <div class="processing-header">
                    <div class="spinner"></div>
                    <div class="processing-text">
                        <strong id="processingTitle">Procesando Ficha con OCR...</strong>
                        <span id="processingStep">Subiendo y analizando páginas del documento...</span>
                    </div>
                </div>
                <div class="progress-track">
                    <div id="progressBarFill" class="progress-bar-fill"></div>
                </div>
            </div>

            <button type="submit" id="btnSubmitUpload" class="btn btn-primary btn-lg">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="5 3 19 12 5 21 5 3"></polygon>
                </svg>
                Iniciar Procesamiento y Cruce
            </button>
        </div>
    </form>
</div>
