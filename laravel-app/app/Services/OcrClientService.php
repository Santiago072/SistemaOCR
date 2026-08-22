<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OcrClientService
{
    protected string $serviceUrl;

    public function __construct()
    {
        $this->serviceUrl = config('services.ocr.url', env('OCR_SERVICE_URL', 'http://ocr_python_service:8000'));
    }

    /**
     * Envía el archivo PDF al microservicio FastAPI vía HTTP POST /extract
     */
    public function extractFromPdf(string $pdfPath): array
    {
        if (!file_exists($pdfPath)) {
            throw new RuntimeException("Archivo PDF no encontrado en: {$pdfPath}");
        }

        $response = Http::timeout(300)
            ->attach(
                'file',
                file_get_contents($pdfPath),
                basename($pdfPath)
            )
            ->post("{$this->serviceUrl}/extract");

        if ($response->failed()) {
            throw new RuntimeException("Error en el microservicio de OCR: " . ($response->json('detail') ?? $response->body()));
        }

        $data = $response->json();
        if (!isset($data['status']) || $data['status'] !== 'success') {
            throw new RuntimeException("Respuesta no exitosa del microservicio de OCR.");
        }

        return $data;
    }
}
