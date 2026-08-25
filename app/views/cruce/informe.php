<?php
$base = defined('BASE_PATH') ? BASE_PATH : '/SistemaOCR/';
?>
<div class="cruce-container">
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
                <h2 class="ficha-title"><?= htmlspecialchars($ficha['programa_formacion'], ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="ficha-meta">
                    <span><strong>Excel:</strong> <?= htmlspecialchars($ficha['archivo_excel_nombre'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
                    <span><strong>PDF:</strong> <?= htmlspecialchars($ficha['archivo_pdf_nombre'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
                    <span><strong>Fecha:</strong> <?= htmlspecialchars($ficha['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (!empty($ficha['tiempo_procesamiento_seg'])): ?>
                        <?php 
                        $seg = (float)$ficha['tiempo_procesamiento_seg'];
                        $minStr = floor($seg / 60) > 0 ? floor($seg / 60) . 'm ' : '';
                        $secStr = round(fmod($seg, 60), 1) . 's';
                        ?>
                        <span><strong>Tiempo OCR:</strong> <span style="color: #4338ca; font-weight: 700;">⏱ <?= $minStr . $secStr ?></span></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="header-actions-group">
                <button type="button" id="btnEliminarReprocesar" class="btn btn-danger btn-lg" data-ficha-id="<?= (int)$ficha['id'] ?>" data-codigo="<?= htmlspecialchars($ficha['codigo_ficha'], ENT_QUOTES, 'UTF-8') ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    Borrar Ficha y Reprocesar
                </button>
                <button type="button" id="btnImportarFinal" class="btn btn-primary btn-lg" data-ficha-id="<?= (int)$ficha['id'] ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Importar Aprobados a BD
                </button>
            </div>
        </div>
    </div>

    <!-- Tarjetas de Métricas de Cruce -->
    <div class="metrics-grid">
        <div class="metric-card metric-total filter-btn active" data-filter="ALL">
            <div class="metric-value"><?= $estadisticas['total'] ?></div>
            <div class="metric-label">Total Procesados</div>
        </div>
        <div class="metric-card metric-success filter-btn" data-filter="CONCILIADO">
            <div class="metric-value"><?= $estadisticas['conciliados'] ?></div>
            <div class="metric-label">Conciliados (100%)</div>
        </div>
        <div class="metric-card metric-warning filter-btn" data-filter="DIFERENCIA_NOMBRE">
            <div class="metric-value"><?= $estadisticas['diferencias'] ?></div>
            <div class="metric-label">Diferencia en Nombres</div>
        </div>
        <div class="metric-card metric-danger filter-btn" data-filter="FALTANTE_PDF">
            <div class="metric-value"><?= $estadisticas['faltantes'] ?></div>
            <div class="metric-label">Faltantes en PDF</div>
        </div>
        <div class="metric-card metric-secondary filter-btn" data-filter="SOBRANTE_PDF">
            <div class="metric-value"><?= $estadisticas['sobrantes'] ?></div>
            <div class="metric-label">Sobrantes en PDF</div>
        </div>
    </div>

    <!-- Tabla Detallada de Comparación Cruzada -->
    <div class="card">
        <div class="card-header">
            <h3>Matriz de Cruce y Validación Documental</h3>
            <div class="search-box">
                <input type="text" id="tableSearchInput" placeholder="Buscar por documento o nombre..." class="search-input">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table cruce-table" id="cruceDataTable">
                    <thead>
                        <tr>
                            <th>Estado de Cruce</th>
                            <th>Datos en Reporte Excel</th>
                            <th>Datos Extraídos (OCR / PDF417)</th>
                            <th>Similitud</th>
                            <th>Observaciones</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($informe as $item): ?>
                            <tr class="cruce-row" data-estado="<?= $item['estado_cruce'] ?>">
                                <td>
                                    <?php
                                    $badge = match($item['estado_cruce']) {
                                        'CONCILIADO'         => ['class' => 'badge-success', 'text' => 'Conciliado'],
                                        'DIFERENCIA_NOMBRE'  => ['class' => 'badge-warning', 'text' => 'Diferencia Nombre'],
                                        'FALTANTE_PDF'       => ['class' => 'badge-danger',  'text' => 'Falta Documento'],
                                        'SOBRANTE_PDF'       => ['class' => 'badge-info',    'text' => 'No en Lista'],
                                        default              => ['class' => 'badge-neutral', 'text' => 'Ilegible']
                                    };
                                    ?>
                                    <span class="badge <?= $badge['class'] ?>"><?= $badge['text'] ?></span>
                                    <?php if ($item['validado_manualmente']): ?>
                                        <span class="badge badge-primary" title="Aprobado manualmente">Manual</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($item['excel_num_doc'])): ?>
                                        <div class="participant-info">
                                            <strong><?= htmlspecialchars($item['excel_tipo_doc'] . ' ' . $item['excel_num_doc'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            <span><?= htmlspecialchars($item['excel_nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <small class="text-muted">Estado: <?= htmlspecialchars($item['excel_estado'] ?? 'Preinscrito', ENT_QUOTES, 'UTF-8') ?></small>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted"><em>No registrado en Excel</em></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($item['ocr_num_doc'])): ?>
                                        <div class="participant-info">
                                            <strong><?= htmlspecialchars(($item['ocr_tipo_doc'] ?? 'CC') . ' ' . $item['ocr_num_doc'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            <span><?= htmlspecialchars($item['ocr_nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                            <small class="text-muted">Pág. <?= (int)$item['pdf_pagina'] ?> (<?= htmlspecialchars($item['metodo_extraccion'] ?? 'OCR', ENT_QUOTES, 'UTF-8') ?>)</small>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-danger"><em>No detectado en PDF</em></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((float)$item['similitud_nombres_porcentaje'] > 0): ?>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar" style="width: <?= (float)$item['similitud_nombres_porcentaje'] ?>%;"></div>
                                            <span><?= (float)$item['similitud_nombres_porcentaje'] ?>%</span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">0%</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="obs-text"><?= htmlspecialchars($item['observaciones'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons-stack">
                                        <?php if (!empty($item['ruta_imagen_recorte'])): ?>
                                            <button type="button" class="btn-sm btn-outline btn-view-doc" 
                                                    data-img="<?= $base ?>uploads/recortes/ficha_<?= (int)$ficha['id'] ?>/<?= htmlspecialchars($item['ruta_imagen_recorte'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-doc="<?= htmlspecialchars($item['ocr_num_doc'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                    data-nombre="<?= htmlspecialchars($item['ocr_nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                Ver Cédula
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($item['estado_cruce'] === 'DIFERENCIA_NOMBRE'): ?>
                                            <button type="button" class="btn-sm btn-primary btn-validar-manual" data-cruce-id="<?= (int)$item['cruce_id'] ?>">
                                                Aprobar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Visor de Cédula -->
<div id="modalVisor" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Documento de Identidad</h3>
            <button type="button" class="modal-close" id="modalCloseBtn">&times;</button>
        </div>
        <div class="modal-body modal-image-container">
            <img id="modalDocImg" src="" alt="Cédula">
        </div>
    </div>
</div>
