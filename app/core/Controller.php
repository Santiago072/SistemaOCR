<?php

namespace App\Core;

/**
 * Clase Base para Controladores MVC
 */
abstract class Controller
{
    /**
     * Renderiza una vista dentro del layout principal
     */
    protected function view(string $viewPath, array $data = [], string $layout = 'layouts/main'): void
    {
        // Extraer variables de forma segura
        extract($data);

        // Variables globales de acceso frecuente disponibles en todas las vistas
        $base = defined('BASE_PATH') ? BASE_PATH : '/SistemaOCR/';
        $csrfToken = \App\Core\Security::generateCsrfToken();

        // Capturar contenido de la vista
        $viewFile = __DIR__ . '/../views/' . $viewPath . '.php';
        if (!file_exists($viewFile)) {
            throw new \RuntimeException("Vista no encontrada: {$viewFile}");
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Cargar layout si existe
        if ($layout) {
            $layoutFile = __DIR__ . '/../views/' . $layout . '.php';
            if (file_exists($layoutFile)) {
                require $layoutFile;
                return;
            }
        }

        echo $content;
    }

    /**
     * Retorna una respuesta JSON segura
     */
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Redirección segura usando index.php?ruta= para compatibilidad con XAMPP sin mod_rewrite estricto
     */
    protected function redirect(string $url): void
    {
        $base = defined('BASE_PATH') ? BASE_PATH : '/SistemaOCR/';
        $baseClean = rtrim($base, '/');
        header("Location: {$baseClean}/index.php?ruta=" . ltrim($url, '/'));
        exit;
    }
}
