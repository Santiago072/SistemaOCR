<?php
/**
 * Redirección automática de Apache (XAMPP) a la carpeta pública de Laravel
 */
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$query = $_SERVER['QUERY_STRING'] ?? '';

// Si accede directamente por Apache XAMPP (http://localhost/SistemaOCR/)
header('Location: /SistemaOCR/laravel-app/public/' . ($query ? '?' . $query : ''));
exit;
