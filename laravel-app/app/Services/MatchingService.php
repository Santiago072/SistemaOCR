<?php

namespace App\Services;

use App\Models\Ficha;
use App\Models\AspiranteExcel;
use App\Models\DocumentoPdfOcr;
use App\Models\CruceConciliacion;
use Illuminate\Support\Facades\DB;

class MatchingService
{
    /**
     * Ejecuta el cruce y conciliación documental entre aspirantes de Excel y documentos extraídos por OCR.
     */
    public function ejecutarCruce(int $fichaId): array
    {
        return DB::transaction(function () use ($fichaId) {
            $ficha = Ficha::findOrFail($fichaId);

            // Limpiar cruces anteriores si existen
            CruceConciliacion::where('ficha_id', $fichaId)->delete();

            $aspirantes = AspiranteExcel::where('ficha_id', $fichaId)->get();
            $documentos = DocumentoPdfOcr::where('ficha_id', $fichaId)->get();

            $docsPorNumero = [];
            foreach ($documentos as $doc) {
                $numNorm = $this->normalizarNumeroDocumento($doc->numero_documento);
                if ($numNorm !== '') {
                    $docsPorNumero[$numNorm][] = $doc;
                }
            }

            $documentosUsadosIds = [];
            $conciliados = 0;
            $diferencias = 0;
            $faltantes = 0;

            // 1. Cruzar cada aspirante de Excel contra los documentos OCR
            foreach ($aspirantes as $asp) {
                $docNumeroAsp = $this->normalizarNumeroDocumento($asp->numero_documento);
                $docCandidatos = $docsPorNumero[$docNumeroAsp] ?? [];

                // Si no hay documento con ese número exacto, buscar por coincidencia cercana solo si los nombres coinciden
                if (empty($docCandidatos)) {
                    foreach ($documentos as $doc) {
                        if (in_array($doc->id, $documentosUsadosIds)) continue;
                        $docNum = $this->normalizarNumeroDocumento($doc->numero_documento);
                        if ($docNum === '' || strlen($docNum) < 7) continue;

                        $simNombres = $this->calcularSimilitudNombres($asp->nombre_completo, $doc->nombre_completo_ocr);
                        $dist = levenshtein($docNumeroAsp, $docNum);
                        if ($dist <= 1 && $simNombres >= 70.0) {
                            $docCandidatos[] = $doc;
                            break;
                        }
                    }
                }

                if (empty($docCandidatos)) {
                    // FALTANTE EN PDF
                    CruceConciliacion::create([
                        'ficha_id'                     => $fichaId,
                        'aspirante_excel_id'           => $asp->id,
                        'documento_pdf_id'             => null,
                        'estado_cruce'                 => 'FALTANTE_PDF',
                        'similitud_nombres_porcentaje' => 0.00,
                        'observaciones'                => 'El participante está en el reporte de Excel pero no se encontró su documento en el PDF.',
                        'validado_manualmente'         => false
                    ]);
                    $faltantes++;
                } else {
                    // Seleccionar el mejor documento candidato
                    $mejorDoc = null;
                    $mejorSimilitud = -1.0;

                    foreach ($docCandidatos as $cand) {
                        if (in_array($cand->id, $documentosUsadosIds)) continue;
                        $sim = $this->calcularSimilitudNombres($asp->nombre_completo, $cand->nombre_completo_ocr);
                        if ($sim > $mejorSimilitud) {
                            $mejorSimilitud = $sim;
                            $mejorDoc = $cand;
                        }
                    }

                    if (!$mejorDoc) {
                        $mejorDoc = $docCandidatos[0];
                        $mejorSimilitud = $this->calcularSimilitudNombres($asp->nombre_completo, $mejorDoc->nombre_completo_ocr);
                    }

                    $documentosUsadosIds[] = $mejorDoc->id;

                    // Evaluar similitud de nombres
                    if ($mejorSimilitud >= 80.0) {
                        $estado = 'CONCILIADO';
                        $obs = ($mejorSimilitud >= 99.0)
                            ? 'Coincidencia exacta de documento y nombres.'
                            : sprintf('Coincidencia exacta de documento y alta similitud en nombres (%.2f%%).', $mejorSimilitud);
                        $conciliados++;
                    } else {
                        $estado = 'DIFERENCIA_NOMBRE';
                        $obs = sprintf('El número de documento coincide pero los nombres difieren (Similitud: %.2f%%). Revisión requerida.', $mejorSimilitud);
                        $diferencias++;
                    }

                    CruceConciliacion::create([
                        'ficha_id'                     => $fichaId,
                        'aspirante_excel_id'           => $asp->id,
                        'documento_pdf_id'             => $mejorDoc->id,
                        'estado_cruce'                 => $estado,
                        'similitud_nombres_porcentaje' => $mejorSimilitud,
                        'observaciones'                => $obs,
                        'validado_manualmente'         => false
                    ]);
                }
            }

            // 2. Documentos sobrantes en el PDF que no están en el Excel (descartando reversos)
            $sobrantes = 0;
            foreach ($documentos as $doc) {
                if (in_array($doc->id, $documentosUsadosIds)) continue;

                $numDoc = $this->normalizarNumeroDocumento($doc->numero_documento);
                $nomDoc = $this->normalizarTexto($doc->nombre_completo_ocr);

                // Descartar si es reverso de un documento ya usado
                $esReverso = false;
                foreach ($documentosUsadosIds as $usadoId) {
                    $usado = $documentos->firstWhere('id', $usadoId);
                    if ($usado && $numDoc !== '' && $this->normalizarNumeroDocumento($usado->numero_documento) === $numDoc) {
                        $esReverso = true;
                        break;
                    }
                }

                if ($esReverso) continue;

                if (empty($numDoc) && empty($nomDoc)) {
                    $estado = 'ILEGIBLE';
                    $obs = 'Página del PDF sin datos legibles de documento o nombre.';
                } else {
                    $estado = 'SOBRANTE_PDF';
                    $obs = "Documento No. {$doc->numero_documento} presente en el PDF pero no está en la lista de inscritos de esta ficha.";
                }

                CruceConciliacion::create([
                    'ficha_id'                     => $fichaId,
                    'aspirante_excel_id'           => null,
                    'documento_pdf_id'             => $doc->id,
                    'estado_cruce'                 => $estado,
                    'similitud_nombres_porcentaje' => 0.00,
                    'observaciones'                => $obs,
                    'validado_manualmente'         => false
                ]);
                $sobrantes++;
            }

            // Actualizar estado de la Ficha
            $ficha->update(['estado' => 'CRUCE_COMPLETADO']);

            return [
                'total'       => count($aspirantes) + $sobrantes,
                'conciliados' => $conciliados,
                'diferencias' => $diferencias,
                'faltantes'   => $faltantes,
                'sobrantes'   => $sobrantes
            ];
        });
    }

    public function calcularSimilitudNombres(?string $nombre1, ?string $nombre2): float
    {
        $n1 = $this->normalizarTexto($nombre1);
        $n2 = $this->normalizarTexto($nombre2);

        if (empty($n1) || empty($n2)) return 0.0;
        if ($n1 === $n2) return 100.0;

        $toks1 = array_unique(explode(' ', $n1));
        $toks2 = array_unique(explode(' ', $n2));

        $coincidentes = 0;
        foreach ($toks1 as $t1) {
            foreach ($toks2 as $t2) {
                if ($t1 === $t2 || (strlen($t1) >= 4 && strlen($t2) >= 4 && levenshtein($t1, $t2) <= 1)) {
                    $coincidentes++;
                    break;
                }
            }
        }

        $totalTokens = max(count($toks1), count($toks2));
        $tokenScore = $totalTokens > 0 ? ($coincidentes / $totalTokens) * 100.0 : 0.0;

        similar_text($n1, $n2, $charPercent);

        return round(($tokenScore * 0.7) + ($charPercent * 0.3), 2);
    }

    private function normalizarNumeroDocumento(?string $num): string
    {
        if (!$num) return '';
        $limpio = preg_replace('/[^\d]/', '', $num);
        return ltrim($limpio, '0');
    }

    private function normalizarTexto(?string $text): string
    {
        if (!$text) return '';
        $text = strtoupper(trim($text));
        $unaccented = strtr($text, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N'
        ]);

        // Normalizar partículas compuestas comunes (DE LA / DELA, DE LOS / DELOS)
        $unaccented = preg_replace('/\bDELA\b/', 'DE LA', $unaccented);
        $unaccented = preg_replace('/\bDELOS\b/', 'DE LOS', $unaccented);
        $unaccented = preg_replace('/\bDELAS\b/', 'DE LAS', $unaccented);

        $clean = preg_replace('/[^A-Z0-9\s]/', '', $unaccented);
        return preg_replace('/\s+/', ' ', $clean);
    }
}
