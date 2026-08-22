<?php
use App\Core\Security;
$base = defined('BASE_PATH') ? BASE_PATH : '/SistemaOCR/';
$csrfToken = Security::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="base-path" content="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($titulo ?? 'Sistema OCR & Conciliación', ENT_QUOTES, 'UTF-8') ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS Modular -->
    <link rel="stylesheet" href="<?= $base ?>public/css/variables.css">
    <link rel="stylesheet" href="<?= $base ?>public/css/layout.css">
    <link rel="stylesheet" href="<?= $base ?>public/css/components.css">
    <?php if (isset($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
            <link rel="stylesheet" href="<?= $base ?>public/css/<?= htmlspecialchars($css, ENT_QUOTES, 'UTF-8') ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <aside class="app-sidebar">
            <div class="sidebar-brand">
                <div class="brand-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="9" y1="3" x2="9" y2="21"></line>
                        <path d="M14 9h4"></path>
                        <path d="M14 13h4"></path>
                        <path d="M14 17h4"></path>
                    </svg>
                </div>
                <div class="brand-text">
                    <h2>Sistema OCR</h2>
                    <span>Conciliación Fichas</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="<?= $base ?>index.php?ruta=home/index" class="nav-item <?= (!isset($_GET['ruta']) || $_GET['ruta'] === 'home/index') ? 'active' : '' ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    <span>Fichas & Dashboard</span>
                </a>
                <a href="<?= $base ?>index.php?ruta=ficha/subir" class="nav-item <?= (isset($_GET['ruta']) && $_GET['ruta'] === 'ficha/subir') ? 'active' : '' ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    <span>Cargar Ficha (Excel/PDF)</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="system-status">
                    <span class="status-indicator online"></span>
                    <span class="status-label">Motor OCR: Listo</span>
                </div>
            </div>
        </aside>

        <!-- Main Wrapper -->
        <main class="app-main">
            <!-- Top Header -->
            <header class="app-header">
                <div class="header-title">
                    <h1><?= htmlspecialchars($titulo ?? 'Panel Principal', ENT_QUOTES, 'UTF-8') ?></h1>
                </div>
                <div class="header-actions">
                    <div class="badge badge-info">Ambiente: <?= htmlspecialchars(getenv('APP_ENV') ?: 'Local', ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </header>

            <!-- Main Content Container -->
            <div class="app-content">
                <?= $content ?>
            </div>
        </main>
    </div>

    <!-- Scripts Globales Modulares (Sin código inline) -->
    <script src="<?= $base ?>public/js/app.js"></script>
    <?php if (isset($extraJs)): ?>
        <?php foreach ($extraJs as $js): ?>
            <script src="<?= $base ?>public/js/<?= htmlspecialchars($js, ENT_QUOTES, 'UTF-8') ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
