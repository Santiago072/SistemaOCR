/**
 * JavaScript Principal y Utilidades de Seguridad (Sin código inline)
 */
document.addEventListener('DOMContentLoaded', () => {
    // Configurar token CSRF en todas las peticiones Fetch automáticas
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const basePath = document.querySelector('meta[name="base-path"]')?.getAttribute('content') || '/SistemaOCR/';

    window.SistemaOCR = {
        csrfToken,
        basePath,

        /**
         * Wrapper seguro para peticiones fetch con CSRF
         */
        async fetchSecure(url, options = {}) {
            const defaultHeaders = {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            };

            if (options.body && !(options.body instanceof FormData)) {
                defaultHeaders['Content-Type'] = 'application/json';
            }

            options.headers = {
                ...defaultHeaders,
                ...(options.headers || {})
            };

            return fetch(url, options);
        }
    };

    // Garantizar que todos los elementos <a> con clase btn funcionen al hacer click
    // Esto resuelve problemas con CSS overlays o pointer-events en algunos navegadores
    document.querySelectorAll('a.btn, a.btn-sm, a.btn-outline, a.nav-item').forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href && href !== '#' && !href.startsWith('javascript')) {
                // Permitir comportamiento nativo (no prevenir)
                // Solo forzar navegación si el default fue prevenido por algún listener externo
                if (e.defaultPrevented) {
                    e.stopPropagation();
                    window.location.href = href;
                }
            }
        });
    });

    console.log('Sistema OCR inicializado. CSRF y navegación activos.');
});
