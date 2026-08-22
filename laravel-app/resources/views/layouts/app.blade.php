<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sistema OCR & Conciliación SENA')</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS Modular -->
    <link rel="stylesheet" href="{{ asset('css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('css/layout.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}">
    @stack('styles')
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
                <a href="{{ route('home') }}" class="nav-item {{ request()->routeIs('home') ? 'active' : '' }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    <span>Fichas & Dashboard</span>
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
                    <h1>@yield('page_title', 'Panel Principal')</h1>
                </div>
                <div class="header-actions">
                    <div class="badge badge-info">Ambiente: {{ config('app.env') }}</div>
                </div>
            </header>

            <!-- Main Content Container -->
            <div class="app-content">
                @yield('content')
            </div>
        </main>
    </div>

    <!-- Scripts Globales -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>
