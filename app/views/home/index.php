<div class="home-container">
    <!-- Header Banner -->
    <div class="page-banner">
        <div class="banner-content">
            <h2>Conciliación de Inscripciones y Verificación OCR</h2>
            <p>Procesa reportes de inscripciones en Excel y contrástalos con los documentos de identidad en PDF mediante lectura de código PDF417 y OCR.</p>
        </div>
        <div class="banner-actions">
            <a href="<?= $base ?>index.php?ruta=ficha/subir" class="btn btn-primary" id="btnNuevaFicha">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Nueva Ficha (Excel + PDF)
            </a>
        </div>
    </div>

    <!-- Lista de Fichas -->
    <div class="card">
        <div class="card-header">
            <h3>Fichas Procesadas</h3>
            <span class="text-muted">Total: <?= count($fichas) ?></span>
        </div>
        <div class="card-body">
            <?php if (empty($fichas)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                    </div>
                    <h4>No hay fichas cargadas todavía</h4>
                    <p>Comienza subiendo el reporte de inscripciones en Excel y el archivo PDF de cédulas de los participantes.</p>
                    <a href="<?= $base ?>index.php?ruta=ficha/subir" class="btn btn-secondary" id="btnCargarPrimera">Cargar Primera Ficha</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Código Ficha</th>
                                <th>Programa de Formación</th>
                                <th>Inscritos</th>
                                <th>Estado</th>
                                <th>Fecha Carga</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fichas as $f): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($f['codigo_ficha'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <td><?= htmlspecialchars($f['programa_formacion'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span class="badge badge-neutral"><?= (int)$f['total_inscritos'] ?></span></td>
                                    <td>
                                        <?php
                                        $badgeClass = match($f['estado']) {
                                            'CRUCE_COMPLETADO' => 'badge-success',
                                            'IMPORTADA'        => 'badge-primary',
                                            'PROCESANDO_OCR'   => 'badge-warning',
                                            default            => 'badge-neutral'
                                        };
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($f['estado'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($f['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td style="white-space: nowrap; width: 140px;">
                                        <div class="action-buttons">
                                            <a href="<?= $base ?>index.php?ruta=cruce/informe&ficha=<?= (int)$f['id'] ?>" class="btn-sm btn-outline">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                                Ver Informe
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
