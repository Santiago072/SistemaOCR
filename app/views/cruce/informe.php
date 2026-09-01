<?php
$base = defined('BASE_PATH') ? BASE_PATH : '/SistemaOCR/';
$trabajoId = $_GET['trabajo'] ?? '';
?>
<?php if (!empty($datosIniciales)): ?>
<script>
    window.DATOS_INICIALES_INFORME = <?= json_encode($datosIniciales, JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php endif; ?>
<div class="cruce-container" data-trabajo="<?= htmlspecialchars($trabajoId, ENT_QUOTES, 'UTF-8') ?>" data-ficha-id="<?= (int)$ficha['id'] ?>">
    <!-- Header y Resumen de Ficha -->
    <div class="ficha-header-card card">
        <div class="ficha-header-top">
            <div class="ficha-header-info">
                <div style="margin-bottom: 8px;">
                    <a href="<?= $base ?>index.php?ruta=home/index" class="btn-sm btn-outline" style="text-decoration: none; padding: 4px 10px; display: inline-flex; align-items: center; gap: 4px;">
                        ← Volver a Fichas
                    </a>
                </div>
                <span class="badge badge-primary">Ficha N° <?= htmlspecialchars($ficha['codigo_ficha'], ENT_QUOTES, 'UTF-8') ?></span>
                <h2 class="ficha-title" id="fichaHeaderTitle"><?= htmlspecialchars($ficha['programa_formacion'], ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="ficha-meta">
                    <span><strong>Excel:</strong> <?= htmlspecialchars($ficha['archivo_excel_nombre'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
                    <span><strong>PDF:</strong> <?= htmlspecialchars($ficha['archivo_pdf_nombre'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
                    <span><strong>Fecha:</strong> <?= htmlspecialchars($ficha['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span id="tiempoLecturaBox" style="margin-left: 10px; font-weight: bold; color: #4f46e5;"></span>
                </div>
            </div>
            <div class="header-actions-group">
                <a href="<?= $base ?>index.php?ruta=cruce/exportarExcel&ficha=<?= (int)$ficha['id'] ?>&trabajo=<?= urlencode($trabajoId) ?>" class="btn" id="btnExportarExcel" style="background: #059669; color: #ffffff; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 0.88rem; transition: background 0.2s;" target="_blank">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="8" y1="13" x2="16" y2="13"></line>
                        <line x1="8" y1="17" x2="16" y2="17"></line>
                        <line x1="10" y1="9" x2="14" y2="9"></line>
                    </svg>
                    Exportar a Excel (.xlsx)
                </a>
                <button type="button" id="btnEliminarReprocesar" class="btn btn-danger" data-ficha-id="<?= (int)$ficha['id'] ?>" data-codigo="<?= htmlspecialchars($ficha['codigo_ficha'], ENT_QUOTES, 'UTF-8') ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    Borrar y Reprocesar
                </button>
                <button type="button" id="btnImportarFinal" class="btn btn-primary" data-ficha-id="<?= (int)$ficha['id'] ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Guardar / Sincronizar en BD
                </button>
            </div>
        </div>
    </div>

    <!-- Barra Superior de Progreso en Vivo y Tiempo Transcurrido -->
    <div class="leyendo-stream-card card" id="barraLeyendoStream" <?= empty($trabajoId) ? 'hidden' : '' ?> style="margin-bottom: 20px; border-left: 4px solid #4f46e5;">
        <div class="leyendo-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span class="leyendo-punto-pulse"></span>
                <strong id="leyendoStreamTexto" style="font-size: 1rem; color: #1e293b;">Iniciando lectura y cotejo de páginas...</strong>
            </div>
            <span id="cronometroStream" style="font-size: 0.9rem; font-weight: 600; color: #64748b;">⏱️ 0s</span>
        </div>
        <div class="leyendo-barra-fondo" style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
            <div class="leyendo-barra-fill" id="leyendoStreamFill" style="width: 0%; height: 100%; background: linear-gradient(90deg, #4f46e5, #06b6d4); transition: width 0.3s;"></div>
        </div>
    </div>

    <!-- Tarjetas de Métricas Resumen -->
    <div class="metrics-grid" style="margin-bottom: 20px;">
        <div class="metric-card metric-success card-filtro-btn" data-filter="ok">
            <div class="metric-value" id="countCorrectas"><?= $estadisticas['conciliados'] ?? 0 ?></div>
            <div class="metric-label">Correctas (Coinciden)</div>
        </div>
        <div class="metric-card metric-warning card-filtro-btn" data-filter="revisar">
            <div class="metric-value" id="countErrores"><?= $estadisticas['diferencias'] ?? 0 ?></div>
            <div class="metric-label">Con discrepancia</div>
        </div>
        <div class="metric-card metric-danger card-filtro-btn" data-filter="sin_listado">
            <div class="metric-value" id="countSoloPdf"><?= $estadisticas['sobrantes'] ?? 0 ?></div>
            <div class="metric-label">Solo en PDF</div>
        </div>
        <div class="metric-card metric-secondary card-filtro-btn" data-filter="faltantes">
            <div class="metric-value" id="countSoloExcel"><?= $estadisticas['faltantes'] ?? 0 ?></div>
            <div class="metric-label">Solo en Excel</div>
        </div>
    </div>

    <!-- TABLA COMPLETA DE RESULTADOS DE PROCESAMIENTO (Fila por Fila en Vivo) -->
    <div class="card" style="margin-bottom: 25px; padding: 20px; overflow-x: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="font-size: 1.1rem; color: #1e293b; margin: 0; font-weight: 700;">Resultados del Cotejo y Verificación</h3>
            <input type="search" id="inputBuscarTabla" placeholder="Filtrar por nombre o documento..." class="search-input" style="max-width: 300px; padding: 6px 12px; font-size: 0.85rem;">
        </div>
        <table class="report-table" id="tablaResultadosGlobal" style="width: 100%; font-size: 0.88rem; border-collapse: collapse;">
            <thead>
                <tr style="background: #f1f5f9; border-bottom: 2px solid #cbd5e1;">
                    <th style="padding: 10px 12px; text-align: center; width: 60px;">Pág</th>
                    <th style="padding: 10px 12px; text-align: center; width: 80px;">Tipo</th>
                    <th style="padding: 10px 12px; text-align: left; width: 140px;">Documento</th>
                    <th style="padding: 10px 12px; text-align: left;">Nombres y Apellidos</th>
                    <th style="padding: 10px 12px; text-align: center; width: 180px;">Estado / Observación</th>
                    <th style="padding: 10px 12px; text-align: center; width: 120px;">Acción</th>
                </tr>
            </thead>
            <tbody id="tablaResultadosBody">
                <!-- Se puebla fila a fila en streaming a medida que se procesa cada página -->
            </tbody>
        </table>
    </div>

    <!-- Modal Visor y Edición de Cédula (Al hacer clic en 'Ver Cédula') -->
    <div id="modalVisorCedula" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: #0f172a; border-radius: 10px; max-width: 1050px; width: 100%; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.6); border: 1px solid #334155;">
            <div style="padding: 12px 20px; background: #1e293b; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #334155;">
                <h4 id="modalTituloCedula" style="color: #f8fafc; margin: 0; font-size: 1.05rem; font-weight: 700;">Visor y Corrección de Documento</h4>
                <button type="button" id="btnCerrarModal" style="background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; line-height: 1;">&times;</button>
            </div>
            <div style="flex: 1; overflow-y: auto; display: flex; flex-wrap: wrap; gap: 20px; padding: 20px; background: #0f172a;">
                <!-- Imagen del documento -->
                <div style="flex: 1 1 500px; display: flex; align-items: center; justify-content: center; background: #1e293b; border-radius: 8px; padding: 15px; border: 1px solid #334155; min-height: 350px;">
                    <img id="modalImagenDoc" src="" alt="Cédula" style="max-width: 100%; max-height: 65vh; object-fit: contain; border-radius: 4px;">
                </div>
                <!-- Formulario de Edición Rápida -->
                <div style="flex: 1 1 320px; background: #1e293b; border-radius: 8px; padding: 20px; border: 1px solid #334155; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <h5 style="color: #38bdf8; margin: 0 0 15px 0; font-size: 0.95rem; font-weight: 700; border-bottom: 1px solid #334155; padding-bottom: 8px;">
                            ✏️ Editar Datos Extraídos
                        </h5>
                        <input type="hidden" id="modalPersonaIdx" value="">
                        
                        <div style="margin-bottom: 12px;">
                            <label style="display: block; color: #cbd5e1; font-size: 0.8rem; font-weight: 600; margin-bottom: 4px;">Tipo de Documento:</label>
                            <select id="modalEditTipo" style="width: 100%; padding: 8px 10px; background: #0f172a; border: 1px solid #475569; border-radius: 6px; color: #f8fafc; font-size: 0.85rem;">
                                <option value="CC">CC - Cédula de Ciudadanía</option>
                                <option value="TI">TI - Tarjeta de Identidad</option>
                                <option value="CE">CE - Cédula de Extranjería</option>
                                <option value="PEP">PEP - Permiso Especial</option>
                                <option value="PPT">PPT - Permiso Protección Temporal</option>
                            </select>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <label style="display: block; color: #cbd5e1; font-size: 0.8rem; font-weight: 600; margin-bottom: 4px;">Número de Documento:</label>
                            <input type="text" id="modalEditDoc" style="width: 100%; padding: 8px 10px; background: #0f172a; border: 1px solid #475569; border-radius: 6px; color: #f8fafc; font-size: 0.85rem;" placeholder="Ej: 1117811433">
                        </div>

                        <div style="margin-bottom: 12px;">
                            <label style="display: block; color: #cbd5e1; font-size: 0.8rem; font-weight: 600; margin-bottom: 4px;">Nombres:</label>
                            <input type="text" id="modalEditNombres" style="width: 100%; padding: 8px 10px; background: #0f172a; border: 1px solid #475569; border-radius: 6px; color: #f8fafc; font-size: 0.85rem;" placeholder="Ej: JUAN CARLOS">
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label style="display: block; color: #cbd5e1; font-size: 0.8rem; font-weight: 600; margin-bottom: 4px;">Apellidos:</label>
                            <input type="text" id="modalEditApellidos" style="width: 100%; padding: 8px 10px; background: #0f172a; border: 1px solid #475569; border-radius: 6px; color: #f8fafc; font-size: 0.85rem;" placeholder="Ej: PEREZ RODRIGUEZ">
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                        <button type="button" id="btnGuardarEdicionModal" style="flex: 1; padding: 10px; background: #4f46e5; hover:background: #4338ca; color: #ffffff; border: none; border-radius: 6px; font-weight: bold; font-size: 0.85rem; cursor: pointer;">
                            💾 Aplicar Cambios
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
