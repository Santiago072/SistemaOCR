@extends('layouts.app')

@section('title', "Informe de Cruce - Ficha {$ficha->codigo_ficha}")
@section('page_title', "Informe de Cruce - Ficha {$ficha->codigo_ficha}")

@push('styles')
<link rel="stylesheet" href="{{ asset('css/cruce.css') }}">
@endpush

@section('content')
<div class="cruce-container">
    <!-- Header y Resumen de Ficha -->
    <div class="ficha-header-card card">
        <div class="ficha-header-top">
            <div class="ficha-header-info">
                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 8px;">
                    <a href="{{ route('home') }}" class="btn-sm btn-outline" style="text-decoration: none; padding: 4px 10px;">
                        ← Volver al Dashboard
                    </a>
                    <span class="badge badge-primary">Ficha N° {{ $ficha->codigo_ficha }}</span>
                </div>
                <h2 class="ficha-title">{{ $ficha->programa_formacion }}</h2>
                <div class="ficha-meta">
                    <span><strong>Excel:</strong> {{ $ficha->archivo_excel_nombre ?? 'N/A' }}</span>
                    <span><strong>PDF:</strong> {{ $ficha->archivo_pdf_nombre ?? 'N/A' }}</span>
                    <span><strong>Fecha:</strong> {{ $ficha->created_at->format('Y-m-d H:i:s') }}</span>
                </div>
            </div>
            <div class="header-actions-group">
                <button type="button" id="btnEliminarReprocesar" class="btn btn-danger btn-lg" data-ficha-id="{{ $ficha->id }}" data-codigo="{{ $ficha->codigo_ficha }}" data-url="{{ route('fichas.eliminar', $ficha->id) }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    Borrar Ficha y Reprocesar
                </button>
                <button type="button" id="btnImportarFinal" class="btn btn-primary btn-lg" data-ficha-id="{{ $ficha->id }}" data-url="{{ route('cruce.importar', $ficha->id) }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Importar Aprobados a BD
                </button>
            </div>
        </div>
    </div>

    <!-- Tarjetas de Métricas de Cruce -->
    <div class="metrics-grid">
        <div class="metric-card metric-total filter-btn active" data-filter="ALL">
            <div class="metric-value">{{ $estadisticas['total'] }}</div>
            <div class="metric-label">Total Procesados</div>
        </div>
        <div class="metric-card metric-success filter-btn" data-filter="CONCILIADO">
            <div class="metric-value">{{ $estadisticas['conciliados'] }}</div>
            <div class="metric-label">Conciliados (100%)</div>
        </div>
        <div class="metric-card metric-warning filter-btn" data-filter="DIFERENCIA_NOMBRE">
            <div class="metric-value">{{ $estadisticas['diferencias'] }}</div>
            <div class="metric-label">Diferencia en Nombres</div>
        </div>
        <div class="metric-card metric-danger filter-btn" data-filter="FALTANTE_PDF">
            <div class="metric-value">{{ $estadisticas['faltantes'] }}</div>
            <div class="metric-label">Faltantes en PDF</div>
        </div>
        <div class="metric-card metric-secondary filter-btn" data-filter="SOBRANTE_PDF">
            <div class="metric-value">{{ $estadisticas['sobrantes'] }}</div>
            <div class="metric-label">Sobrantes en PDF</div>
        </div>
    </div>

    <!-- Tabla Detallada de Comparación Cruzada -->
    <div class="card">
        <div class="card-header">
            <h3>Matriz de Cruce y Validación Documental</h3>
            <div class="search-box">
                <input type="text" id="tableSearchInput" placeholder="Buscar por documento o nombre..." class="search-input">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table cruce-table" id="cruceDataTable">
                    <thead>
                        <tr>
                            <th>Estado de Cruce</th>
                            <th>Datos en Reporte Excel</th>
                            <th>Datos Extraídos (OCR / PDF417)</th>
                            <th>Similitud</th>
                            <th>Observaciones</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cruces as $c)
                            @php
                                $asp = $c->aspiranteExcel;
                                $doc = $c->documentoPdf;
                                $badge = match($c->estado_cruce) {
                                    'CONCILIADO'         => ['class' => 'badge-success', 'text' => 'Conciliado'],
                                    'DIFERENCIA_NOMBRE'  => ['class' => 'badge-warning', 'text' => 'Diferencia Nombre'],
                                    'FALTANTE_PDF'       => ['class' => 'badge-danger',  'text' => 'Falta Documento'],
                                    'SOBRANTE_PDF'       => ['class' => 'badge-info',    'text' => 'No en Lista'],
                                    default              => ['class' => 'badge-neutral', 'text' => 'Ilegible']
                                };
                            @endphp
                            <tr class="cruce-row" data-estado="{{ $c->estado_cruce }}">
                                <td>
                                    <span class="badge {{ $badge['class'] }}">{{ $badge['text'] }}</span>
                                    @if ($c->validado_manualmente)
                                        <span class="badge badge-primary" title="Aprobado manualmente">Manual</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($asp)
                                        <div class="participant-info">
                                            <strong>{{ $asp->tipo_documento }} {{ $asp->numero_documento }}</strong>
                                            <span>{{ $asp->nombre_completo }}</span>
                                            <small class="text-muted">Estado: {{ $asp->estado_inscripcion }}</small>
                                        </div>
                                    @else
                                        <span class="text-muted"><em>No registrado en Excel</em></span>
                                    @endif
                                </td>
                                <td>
                                    @if ($doc && $doc->numero_documento)
                                        <div class="participant-info">
                                            <strong>{{ $doc->tipo_documento ?? 'CC' }} {{ $doc->numero_documento }}</strong>
                                            <span>{{ $doc->nombre_completo_ocr }}</span>
                                            <small class="text-muted">Pág. {{ $doc->numero_pagina }} ({{ $doc->metodo_extraccion }})</small>
                                        </div>
                                    @else
                                        <span class="text-danger"><em>No detectado en PDF</em></span>
                                    @endif
                                </td>
                                <td>
                                    @if ($c->similitud_nombres_porcentaje > 0)
                                        <div class="progress-bar-container">
                                            <div class="progress-bar" style="width: {{ $c->similitud_nombres_porcentaje }}%;"></div>
                                            <span>{{ $c->similitud_nombres_porcentaje }}%</span>
                                        </div>
                                    @else
                                        <span class="text-muted">0%</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="obs-text">{{ $c->observaciones }}</span>
                                </td>
                                <td>
                                    <div class="action-buttons-stack">
                                        @if ($doc && !empty($doc->ruta_imagen_recorte))
                                            <button type="button" class="btn-sm btn-outline btn-view-doc" 
                                                    data-img="{{ asset('storage/recortes/ficha_' . $ficha->id . '/' . $doc->ruta_imagen_recorte) }}"
                                                    data-doc="{{ $doc->numero_documento ?? '' }}"
                                                    data-nombre="{{ $doc->nombre_completo_ocr ?? '' }}">
                                                Ver Cédula
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Visor de Cédula -->
<div id="modalVisor" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Documento de Identidad</h3>
            <button type="button" class="modal-close" id="modalCloseBtn">&times;</button>
        </div>
        <div class="modal-body modal-image-container">
            <img id="modalDocImg" src="" alt="Cédula">
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/cruce.js') }}"></script>
@endpush
