<?php
// ==========================================================
// Front Controller Principal - Sistema OCR
// ==========================================================

// Cargar variables de entorno antes de cualquier operación
require_once __DIR__ . '/config/EnvLoader.php';
\App\Config\EnvLoader::load(__DIR__ . '/.env');

// Autoload de Composer si existe, o fallback SPL seguro
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'App\\';
        $base_dir = __DIR__ . '/app/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    });
}

use App\Core\Container;
use App\Core\Security;

// Inicializar cabeceras de seguridad y sesión segura
Security::setSecurityHeaders();
Security::initSession();

// Configurar base path (Por defecto /SistemaOCR/ para XAMPP local, o / para VPS)
$appBase = getenv('APP_BASE') ?: '/SistemaOCR/';
define('BASE_PATH', $appBase);

// Contenedor DI
$container = new Container();

// Mapa estricto de rutas protegidas
$rutas = [
    // Dashboard y Fichas
    'home/index'           => [\App\Controllers\HomeController::class, 'index'],
    'ficha/index'          => [\App\Controllers\HomeController::class, 'index'],
    'ficha/subir'          => [\App\Controllers\FichaController::class, 'subir'],
    'ficha/procesar'       => [\App\Controllers\FichaController::class, 'procesar'],
    'ficha/detalle'        => [\App\Controllers\FichaController::class, 'detalle'],
    
    // Cruce e Informe
    'cruce/informe'        => [\App\Controllers\CruceController::class, 'informe'],
    'cruce/validar_manual' => [\App\Controllers\CruceController::class, 'validarManual'],
    'cruce/importar'       => [\App\Controllers\CruceController::class, 'importarFinal'],
    'ficha/eliminar'       => [\App\Controllers\FichaController::class, 'eliminar'],
    
    // API / Async endpoints
    'api/estado_proceso'   => [\App\Controllers\ApiController::class, 'estadoProceso']
];

// Obtener ruta de la URL (GET ?ruta=...)
$ruta = $_GET['ruta'] ?? 'home/index';
$ruta = filter_var($ruta, FILTER_DEFAULT);

// Despacho seguro de ruta
if (array_key_exists($ruta, $rutas)) {
    try {
        $controladorClase = $rutas[$ruta][0];
        $metodo = $rutas[$ruta][1];

        // Validar CSRF automáticamente en todas las peticiones POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if (!Security::validateCsrfToken($csrfToken)) {
                http_response_code(403);
                die(json_encode(['error' => 'Token de seguridad inválido o expirado (CSRF).']));
            }
        }

        $controlador = $container->make($controladorClase);
        $controlador->$metodo();
    } catch (\Throwable $e) {
        $debug = getenv('APP_DEBUG') === 'true';
        if ($debug) {
            echo "<h1>Error en la aplicación:</h1><pre>" . htmlspecialchars($e->getMessage() . "\n\n" . $e->getTraceAsString()) . "</pre>";
        } else {
            http_response_code(500);
            echo "<h1>500 - Error interno del servidor</h1><p>Por favor intente nuevamente o contacte al administrador.</p>";
        }
    }
} else {
    http_response_code(404);
    echo "<h1>404 - Página no encontrada</h1>";
}
