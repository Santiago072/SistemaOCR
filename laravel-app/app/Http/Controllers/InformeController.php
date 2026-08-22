<?php

namespace App\Http\Controllers;

use App\Models\Ficha;
use App\Models\CruceConciliacion;
use App\Models\ParticipanteFinal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class InformeController extends Controller
{
    public function show(Ficha $ficha)
    {
        $cruces = CruceConciliacion::with(['aspiranteExcel', 'documentoPdf'])
            ->where('ficha_id', $ficha->id)
            ->orderBy('id', 'asc')
            ->get();

        $estadisticas = [
            'total'        => $cruces->count(),
            'conciliados'  => $cruces->where('estado_cruce', 'CONCILIADO')->count(),
            'diferencias'  => $cruces->where('estado_cruce', 'DIFERENCIA_NOMBRE')->count(),
            'faltantes'    => $cruces->where('estado_cruce', 'FALTANTE_PDF')->count(),
            'sobrantes'    => $cruces->whereIn('estado_cruce', ['SOBRANTE_PDF', 'ILEGIBLE'])->count(),
        ];

        return view('cruce.informe', compact('ficha', 'cruces', 'estadisticas'));
    }

    public function importarFinal(Ficha $ficha)
    {
        try {
            DB::transaction(function () use ($ficha) {
                // Obtener conciliados y validados manualmente
                $crucesAprobados = CruceConciliacion::with(['aspiranteExcel', 'documentoPdf'])
                    ->where('ficha_id', $ficha->id)
                    ->where(function ($q) {
                        $q->where('estado_cruce', 'CONCILIADO')
                          ->orWhere('validado_manualmente', true);
                    })
                    ->get();

                if ($crucesAprobados->isEmpty()) {
                    throw new Exception("No hay registros conciliados o validados para importar.");
                }

                ParticipanteFinal::where('ficha_id', $ficha->id)->delete();

                foreach ($crucesAprobados as $c) {
                    $asp = $c->aspiranteExcel;
                    $doc = $c->documentoPdf;

                    $tipoDoc = $asp->tipo_documento ?? $doc->tipo_documento ?? 'CC';
                    $numeroDoc = $asp->numero_documento ?? $doc->numero_documento;
                    $nombreCompleto = $asp->nombre_completo ?? $doc->nombre_completo_ocr;

                    $nombres = $doc ? trim("{$doc->primer_nombre} {$doc->segundo_nombre}") : '';
                    $apellidos = $doc ? trim("{$doc->primer_apellido} {$doc->segundo_apellido}") : '';

                    if (empty($nombres) && empty($apellidos)) {
                        $parts = explode(' ', $nombreCompleto);
                        $nombres = $parts[0] ?? '';
                        $apellidos = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '';
                    }

                    ParticipanteFinal::create([
                        'ficha_id'           => $ficha->id,
                        'tipo_documento'     => $tipoDoc,
                        'numero_documento'   => $numeroDoc,
                        'nombres'            => $nombres,
                        'apellidos'          => $apellidos,
                        'nombre_completo'    => $nombreCompleto,
                        'genero'             => $doc->genero ?? null,
                        'fecha_nacimiento'   => $doc->fecha_nacimiento ?? null,
                        'rh'                 => $doc->rh ?? null,
                        'estado_inscripcion' => $asp->estado_inscripcion ?? 'Matriculado / Validado',
                        'origen_validacion'  => $c->validado_manualmente ? 'MANUAL_SUPERVISOR' : 'AUTOMATICO_OCR'
                    ]);
                }

                $ficha->update(['estado' => 'IMPORTADA']);
            });

            return response()->json([
                'success' => true,
                'mensaje' => 'Participantes importados a la base de datos final exitosamente.'
            ]);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
