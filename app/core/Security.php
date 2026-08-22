<?php

namespace App\Core;

/**
 * Gestor de Seguridad: CSRF, Sanitización XSS y Validación
 */
class Security
{
    /**
     * Inicia la sesión de forma segura si no está iniciada
     */
    public static function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
            
            session_set_cookie_params([
                'lifetime' => 7200,
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,      // Previene robo de sesión mediante JS (XSS)
                'samesite' => 'Lax'      // Protección contra CSRF
            ]);
            session_start();
        }
    }

    /**
     * Genera o retorna el token CSRF actual
     */
    public static function generateCsrfToken(): string
    {
        self::initSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Valida el token CSRF enviado en peticiones POST
     */
    public static function validateCsrfToken(?string $token): bool
    {
        self::initSession();
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Sanitiza cadenas contra ataques XSS
     */
    public static function sanitize(string $data): string
    {
        return htmlspecialchars(trim($data), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Configura cabeceras de seguridad HTTP
     */
    public static function setSecurityHeaders(): void
    {
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: SAMEORIGIN");
        header("X-XSS-Protection: 1; mode=block");
        header("Referrer-Policy: strict-origin-when-cross-origin");
    }
}
