<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Ficha;
use App\Models\Cruce;

/**
 * Controlador de Inicio / Dashboard Principal
 */
class HomeController extends Controller
{
    private Ficha $fichaModel;
    private Cruce $cruceModel;

    public function __construct(Ficha $fichaModel, Cruce $cruceModel)
    {
        $this->fichaModel = $fichaModel;
        $this->cruceModel = $cruceModel;
    }

    public function index(): void
    {
        $fichas = $this->fichaModel->getAll();

        $this->view('home/index', [
            'fichas' => $fichas,
            'titulo' => 'Sistema OCR - Conciliación Documental',
            'extraJs' => ['dropzone-uploader.js']
        ]);
    }
    public function landing(): void
    {
        $fichas = $this->fichaModel->getAll();
        $totalInscritos = 0;
        foreach ($fichas as $f) {
            $totalInscritos += (int)($f['total_inscritos'] ?? 0);
        }

        $this->view('landing/index', [
            'fichas' => $fichas,
            'totalInscritos' => $totalInscritos,
            'titulo' => 'Sistema OCR — Auditoría y Conciliación Documental Inteligente'
        ], ''); // Layout vacío porque la landing es autosuficiente con su propia estructura premium
    }

}
