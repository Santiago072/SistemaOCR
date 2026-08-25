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
}
