<?php

namespace App\Services;

use RuntimeException;

/**
 * Servicio Puente para Ejecutar el Worker OCR de Python de Forma Segura
 * PASO 5: Captura stderr para monitoreo en tiempo real
 */
class OcrPythonBridgeService
{
    private string $pythonExec;
    private string $serviceUrl = 'http://127.0.0.1:5005';

    public function __construct()
    {
        // 1. Buscar runtime portable de Python relativo a la app o en el entorno del sistema
        $posiblesRutas = [
            'C:\\Users\\Usuario\\AppData\\Local\\Programs\\Python\\Python313\\python.exe',
            realpath(__DIR__ . '/../../python/python.exe'),
            realpath(__DIR__ . '/../../python_ocr/python/python.exe'),
            realpath(__DIR__ . '/../../../Lector de cedulas/python/python.exe'),
        ];

        $ejecutableEncontrado = null;
        foreach ($posiblesRutas as $ruta) {
            if ($ruta && file_exists($ruta)) {
                $ejecutableEncontrado = $ruta;
                break;
            }
        }

        $this->pythonExec = $ejecutableEncontrado ?: (getenv('PYTHON_EXECUTABLE') ?: 'python');
        $this->scriptPath = realpath(__DIR__ . '/../../python_ocr/extractor.py') ?: '';
    }

    /**
     * Comprueba si el microservicio OCR en segundo plano está activo o lo inicia automáticamente
     */
    public function asegurarServicioActivo(): bool
    {
        $ch = curl_init($this->serviceUrl . '/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 1,
            CURLOPT_CONNECTTIMEOUT => 1,
        ]);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return true;
        }

        // Iniciar el servidor OCR nativo de SistemaOCR en segundo plano si no estaba corriendo
        $servidorPath = realpath(__DIR__ . '/../../python_ocr');
        if ($servidorPath) {
            $pythonw = str_ireplace('python.exe', 'pythonw.exe', $this->pythonExec);
            $pyBin = file_exists($pythonw) ? $pythonw : $this->pythonExec;
            
            // Ejecutar desacoplado en segundo plano en Windows
            $cmd = 'powershell -WindowStyle Hidden -Command "Start-Process \\"' . addslashes($pyBin) . '\\" -ArgumentList \\"servidor.py\\" -WorkingDirectory \\"' . addslashes($servidorPath) . '\\""';
            @pclose(@popen($cmd, "r"));
            
            // Esperar activamente hasta 6 segundos a que el servidor esté escuchando
            for ($i = 0; $i < 12; $i++) {
                usleep(500000); // 500ms
                $ch = curl_init($this->serviceUrl . '/');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 1,
                    CURLOPT_CONNECTTIMEOUT => 1,
                ]);
                $res = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($code === 200) {
                    return true;
                }
            }
        }
        return true;
    }

    /**
     * Sube PDF y Excel al microservicio OCR en segundo plano (retorna job_id en < 500ms)
     */
    public function iniciarTrabajo(string $pdfPath, ?string $excelPath = null): array
    {
        $this->asegurarServicioActivo();

        $cfilePdf = new \CURLFile($pdfPath, 'application/pdf', basename($pdfPath));
        $postData = ['documento' => $cfilePdf, 'releer' => '1'];

        if ($excelPath && file_exists($excelPath)) {
            $postData['listado'] = new \CURLFile($excelPath, 'application/vnd.ms-excel', basename($excelPath));
        }

        $ch = curl_init($this->serviceUrl . '/api/subir');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || !$response) {
            throw new RuntimeException("Error al comunicarse con el microservicio OCR: " . ($err ?: 'Sin respuesta'));
        }

        $json = json_decode($response, true);
        if (empty($json['trabajo'])) {
            throw new RuntimeException("Respuesta inválida del microservicio: " . $response);
        }

        return $json;
    }

    /**
     * Obtiene el estado actual del procesamiento
     */
    public function getDatosTrabajo(string $trabajoId): ?array
    {
        $url = $this->serviceUrl . '/api/' . urlencode($trabajoId) . '/datos';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        $datos = json_decode($response, true);
        if (!$datos || !isset($datos['personas'])) {
            return $datos;
        }

        // Asegurar que cada persona tenga la propiedad 'valores' con nombres y apellidos separados
        foreach ($datos['personas'] as &$p) {
            if (!isset($p['valores']) || empty($p['valores'])) {
                $c = $p['campos'] ?? [];
                $nombresVal = (is_array($c['nombres'] ?? null)) ? ($c['nombres']['valor'] ?? '') : ($c['nombres'] ?? '');
                $apellidosVal = (is_array($c['apellidos'] ?? null)) ? ($c['apellidos']['valor'] ?? '') : ($c['apellidos'] ?? '');
                $docVal = (is_array($c['documento'] ?? null)) ? ($c['documento']['valor'] ?? '') : ($c['documento'] ?? ($p['documento'] ?? ''));
                $tipoVal = (is_array($c['tipo_documento'] ?? null)) ? ($c['tipo_documento']['valor'] ?? '') : ($c['tipo_documento'] ?? 'CC');

                $p['valores'] = [
                    'tipo_documento' => $tipoVal ?: 'CC',
                    'documento'      => $docVal,
                    'nombres'        => $nombresVal,
                    'apellidos'      => $apellidosVal,
                ];
            }
        }
        unset($p);

        return $datos;
    }

    /**
     * Obtiene los resultados parciales ya leídos hasta el momento
     */
    public function consultarParcial(string $trabajoId): array
    {
        $ch = curl_init($this->serviceUrl . '/api/' . urlencode($trabajoId) . '/parcial');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response ?: '{}', true) ?: [];
    }

    /**
     * Obtiene el resultado final completo una vez terminado
     */
    public function consultarDatosFinales(string $trabajoId): array
    {
        $ch = curl_init($this->serviceUrl . '/api/' . urlencode($trabajoId) . '/datos');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response ?: '{}', true) ?: [];
    }

    /**
     * Establece callback para procesos de progreso
     * $callback(array $event) donde $event contiene: type, message, page, phase, etc.
     */
    public function setProgressCallback(?callable $callback): self
    {
        $this->progressCallback = $callback;
        return $this;
    }

    /**
     * Procesa un PDF ejecutando el extractor en Python y devuelve un array estructurado
     * 
     * PASO 5: Captura stderr con proc_open para monitoreo en tiempo real
     */
    public function processPdf(string $pdfAbsolutePath, string $outputDirAbsolutePath): array
    {
        if (!file_exists($pdfAbsolutePath)) {
            throw new RuntimeException("El archivo PDF no existe: {$pdfAbsolutePath}");
        }

        if (!file_exists($this->scriptPath)) {
            throw new RuntimeException("El script extractor de Python no fue encontrado en: {$this->scriptPath}");
        }

        if (!is_dir($outputDirAbsolutePath)) {
            mkdir($outputDirAbsolutePath, 0777, true);
        }

        // -X utf8 fuerza salida UTF-8 en Windows (evita CP1252 por defecto)
        $cmd = sprintf(
            '%s -X utf8 %s --pdf %s --output-dir %s',
            escapeshellarg($this->pythonExec),
            escapeshellarg($this->scriptPath),
            escapeshellarg($pdfAbsolutePath),
            escapeshellarg($outputDirAbsolutePath)
        );

        // Descriptor spec: stdin, stdout, stderr como pipes
        $descriptorspec = [
            0 => ['pipe', 'r'],   // stdin
            1 => ['pipe', 'w'],   // stdout
            2 => ['pipe', 'w']    // stderr
        ];

        $pipes = [];
        $process = proc_open($cmd, $descriptorspec, $pipes);

        if (!is_resource($process)) {
            throw new RuntimeException("No se pudo abrir proceso Python");
        }

        // Cerrar stdin (Python no lo necesita)
        fclose($pipes[0]);

        // Variables para acumular salida
        $stdout = '';
        $stderr = '';

        // Configurar pipes como no-blocking para lectura simultánea
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        // Leer mientras el proceso está activo
        while (true) {
            $status = proc_get_status($process);
            
            // Leer de stdout disponible
            while ($chunk = fread($pipes[1], 16384)) {
                $stdout .= $chunk;
            }

            // Leer de stderr disponible línea por línea
            while ($line = fgets($pipes[2], 2048)) {
                $stderr .= $line;
                $this->_parseProgressEvent($line);
            }

            if (!$status['running']) {
                break;
            }

            // Pausa ultra-corta de 5ms para no saturar CPU pero responder de inmediato
            usleep(5000);
        }

        // Leer cualquier remanente final de los streams
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            error_log("Error en Python OCR Worker (exit code {$exitCode}): " . $stderr);
            throw new RuntimeException("Error al ejecutar el procesador OCR. Stderr: " . $stderr);
        }

        // Buscar el bloque JSON en stdout
        $jsonStart = strpos($stdout, '{');
        $jsonEnd = strrpos($stdout, '}');

        if ($jsonStart === false || $jsonEnd === false) {
            error_log("Error: No se encontró JSON en salida de Python. Stdout: " . $stdout . "\nStderr: " . $stderr);
            throw new RuntimeException("Error: Respuesta OCR no contiene JSON válido");
        }

        $jsonString = substr($stdout, $jsonStart, ($jsonEnd - $jsonStart) + 1);

        // Garantizar UTF-8 válido antes de decodificar (Windows puede enviar CP1252)
        if (!mb_check_encoding($jsonString, 'UTF-8')) {
            $jsonString = mb_convert_encoding($jsonString, 'UTF-8', 'Windows-1252');
        }

        $decoded = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Error al decodificar la respuesta JSON de Python: " . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Parsea eventos de progreso desde stderr de Python
     * Formatos esperados:
     * - PROGRESS:5:22 o PROGRESS:5/22
     * - PHASE:OCR_EXTRACTION
     * - DOCUMENT:1117513499
     * - ERROR:mensaje de error
     */
    private function _parseProgressEvent(string $line): void
    {
        $line = trim($line);
        if (empty($line)) {
            return;
        }

        $event = null;

        // PROGRESS:5:22 o PROGRESS:5/22
        if (preg_match('/^PROGRESS:(\d+)[:\/](\d+)/', $line, $m)) {
            $event = [
                'type' => 'PROGRESS',
                'current_page' => (int)$m[1],
                'total_pages' => (int)$m[2],
                'message' => "Procesando página {$m[1]} de {$m[2]}"
            ];
        }
        // PHASE:OCR_EXTRACTION
        elseif (preg_match('/^PHASE:(.+)/', $line, $m)) {
            $phaseName = $m[1];
            $phaseLabel = $this->_getPhaseLabel($phaseName);
            $event = [
                'type' => 'PHASE',
                'phase' => $phaseName,
                'message' => "Fase: {$phaseLabel}"
            ];
        }
        // DOCUMENT:1117513499
        elseif (preg_match('/^DOCUMENT:(.+)/', $line, $m)) {
            $event = [
                'type' => 'DOCUMENT',
                'document_id' => $m[1],
                'message' => "Documento extraído: {$m[1]}"
            ];
        }
        // ERROR:mensaje
        elseif (preg_match('/^ERROR:(.+)/', $line, $m)) {
            $event = [
                'type' => 'ERROR',
                'message' => $m[1]
            ];
        }
        // WARNING:mensaje
        elseif (preg_match('/^WARNING:(.+)/', $line, $m)) {
            $event = [
                'type' => 'WARNING',
                'message' => $m[1]
            ];
        }

        // Llamar callback si existe
        if ($event && $this->progressCallback) {
            call_user_func($this->progressCallback, $event);
        }
    }

    /**
     * Convierte código de fase a etiqueta legible
     */
    private function _getPhaseLabel(string $phaseCode): string
    {
        $labels = [
            'FASE_0_RELEVANCIA' => 'Analizando relevancia de páginas',
            'FASE_1_BARCODE' => 'Extrayendo códigos de barras PDF417',
            'FASE_2_OCR_ADAPTATIVO' => 'Procesando OCR adaptativo',
            'FASE_3_FUSION_DOCUMENTOS' => 'Fusionando frente+reverso'
        ];

        return $labels[$phaseCode] ?? $phaseCode;
    }
}
