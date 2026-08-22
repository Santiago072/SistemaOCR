<?php

namespace App\Http\Controllers;

use App\Models\Ficha;
use App\Models\AspiranteExcel;
use App\Models\DocumentoPdfOcr;
use App\Models\ParticipanteFinal;
use App\Services\ExcelImportService;
use App\Services\OcrClientService;
use App\Services\MatchingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Exception;

class FichaController extends Controller
{
    protected ExcelImportService $excelService;
    protected OcrClientService $ocrService;
    protected MatchingService $matchingService;

    public function __construct(
        ExcelImportService $excelService,
        OcrClientService $ocrService,
        MatchingService $matchingService
    ) {
        $this->excelService = $excelService;
        $this->ocrService = $ocrService;
        $this->matchingService = $matchingService;
    }

    public function index()
    {
        $fichas = Ficha::orderBy('created_at', 'desc')->get();
        return view('home.index', compact('fichas'));
    }

    public function procesar(Request $request)
    {
        $request->validate([
            'archivo_excel' => 'required|file|mimes:xlsx,xls',
            'archivo_pdf'   => 'required|file|mimes:pdf',
        ]);

        try {
            $excelFile = $request->file('archivo_excel');
            $pdfFile = $request->file('archivo_pdf');

            // Guardar archivos en el storage público
            $excelPath = $excelFile->store('uploads', 'public');
            $pdfPath = $pdfFile->store('uploads', 'public');

            $excelFullPath = Storage::disk('public')->path($excelPath);
            $pdfFullPath = Storage::disk('public')->path($pdfPath);

            // 1. Parsear reporte Excel
            $excelData = $this->excelService->parseInscripciones($excelFullPath);
            $codigoFicha = $excelData['codigo_ficha'];
            $programa = $excelData['programa_formacion'];
            $aspirantes = $excelData['aspirantes'];

            // 2. Guardar o actualizar Ficha
            $ficha = Ficha::updateOrCreate(
                ['codigo_ficha' => $codigoFicha],
                [
                    'programa_formacion'   => $programa,
                    'total_inscritos'      => count($aspirantes),
                    'archivo_excel_nombre' => $excelFile->getClientOriginalName(),
                    'archivo_pdf_nombre'   => $pdfFile->getClientOriginalName(),
                    'estado'               => 'PROCESANDO_OCR'
                ]
            );

            // 3. Guardar aspirantes de Excel
            AspiranteExcel::where('ficha_id', $ficha->id)->delete();
            foreach ($aspirantes as $asp) {
                AspiranteExcel::create([
                    'ficha_id'           => $ficha->id,
                    'tipo_documento'     => $asp['tipo_documento'],
                    'numero_documento'   => $asp['numero_documento'],
                    'nombre_completo'    => $asp['nombre_completo'],
                    'estado_inscripcion' => $asp['estado_inscripcion']
                ]);
            }

            // 4. Procesar PDF en microservicio FastAPI
            $ocrResponse = $this->ocrService->extractFromPdf($pdfFullPath);
            $paginas = $ocrResponse['paginas'] ?? [];

            // 5. Guardar documentos OCR en BD
            DocumentoPdfOcr::where('ficha_id', $ficha->id)->delete();
            foreach ($paginas as $p) {
                DocumentoPdfOcr::create([
                    'ficha_id'            => $ficha->id,
                    'numero_pagina'       => $p['numero_pagina'],
                    'tipo_documento'      => $p['tipo_documento'] ?? 'CC',
                    'numero_documento'    => $p['numero_documento'] ?? null,
                    'primer_apellido'     => $p['primer_apellido'] ?? null,
                    'segundo_apellido'    => $p['segundo_apellido'] ?? null,
                    'primer_nombre'       => $p['primer_nombre'] ?? null,
                    'segundo_nombre'      => $p['segundo_nombre'] ?? null,
                    'nombre_completo_ocr' => $p['nombre_completo_ocr'] ?? null,
                    'genero'              => $p['genero'] ?? null,
                    'fecha_nacimiento'    => $p['fecha_nacimiento'] ?? null,
                    'rh'                  => $p['rh'] ?? null,
                    'metodo_extraccion'   => $p['metodo_extraccion'] ?? 'PDF417',
                    'confianza_score'     => $p['confianza_score'] ?? 0.0,
                    'ruta_imagen_recorte' => $p['ruta_imagen_recorte'] ?? null,
                    'raw_data_json'       => $p['raw_data_json'] ?? []
                ]);
            }

            // 6. Ejecutar Cruce y Conciliación
            $this->matchingService->ejecutarCruce($ficha->id);

            if ($request->ajax()) {
                return response()->json([
                    'success'      => true,
                    'ficha_id'     => $ficha->id,
                    'redirect_url' => route('cruce.informe', $ficha->id)
                ]);
            }

            return redirect()->route('cruce.informe', $ficha->id);

        } catch (Exception $e) {
            if ($request->ajax()) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function eliminar(Ficha $ficha)
    {
        $ficha->delete();
        return response()->json([
            'success' => true,
            'mensaje' => 'La ficha y sus registros han sido eliminados correctamente.'
        ]);
    }
}
