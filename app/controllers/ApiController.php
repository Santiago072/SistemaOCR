<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Ficha;

/**
 * Controlador de API para verificación asíncrona de estados
 */
class ApiController extends Controller
{
    private Ficha $fichaModel;

    public function __construct(Ficha $fichaModel)
    {
        $this->fichaModel = $fichaModel;
    }

    public function estadoProceso(): void
    {
        $fichaId = (int)($_GET['ficha'] ?? 0);
        $ficha = $this->fichaModel->findById($fichaId);

        if (!$ficha) {
            $this->json(['error' => 'Ficha no encontrada'], 404);
        }

        $this->json([
            'id' => $ficha['id'],
            'estado' => $ficha['estado'],
            'total_inscritos' => $ficha['total_inscritos']
        ]);
    }
}
