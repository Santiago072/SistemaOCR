@extends('layouts.app')

@section('title', 'Fichas & Dashboard - Sistema OCR SENA')
@section('page_title', 'Fichas & Dashboard')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/dropzone.css') }}">
@endpush

@section('content')
<div class="home-container">
    <!-- Header Banner -->
    <div class="page-banner">
        <div class="banner-content">
            <h2>Conciliación de Inscripciones y Verificación OCR</h2>
            <p>Procesa reportes de inscripciones en Excel y contrástalos con los documentos de identidad en PDF mediante lectura de código PDF417 y OCR neuronal.</p>
        </div>
    </div>

    <!-- Formulario de Carga Directa -->
    <form id="uploadForm" class="upload-form" action="{{ route('fichas.procesar') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="upload-grid">
            <!-- Zona 1: Archivo Excel -->
            <div class="card upload-card" id="dropzoneExcel">
                <div class="card-header">
                    <div class="card-header-icon excel-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="8" y1="13" x2="16" y2="13"></line>
                            <line x1="8" y1="17" x2="16" y2="17"></line>
                            <line x1="10" y1="9" x2="8" y2="9"></line>
                        </svg>
                    </div>
                    <h3>1. Reporte de Inscripciones (Excel)</h3>
                </div>
                <div class="card-body dropzone-body">
                    <input type="file" id="archivo_excel" name="archivo_excel" accept=".xlsx, .xls" class="file-input" required>
                    <div class="dropzone-content">
                        <div class="drop-icon">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                        </div>
                        <p class="drop-text">Arrastra el archivo Excel aquí o <span class="browse-link">examinar</span></p>
                        <span class="file-format-hint">Formatos: .xlsx, .xls (Estructura Sofía Plus)</span>
                        <div class="selected-file-info" id="excelFileInfo"></div>
                    </div>
                </div>
            </div>

            <!-- Zona 2: Archivo PDF Documentación -->
            <div class="card upload-card" id="dropzonePdf">
                <div class="card-header">
                    <div class="card-header-icon pdf-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                        </svg>
                    </div>
                    <h3>2. Documentos de Identidad (PDF)</h3>
                </div>
                <div class="card-body dropzone-body">
                    <input type="file" id="archivo_pdf" name="archivo_pdf" accept=".pdf" class="file-input" required>
                    <div class="dropzone-content">
                        <div class="drop-icon">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                        </div>
                        <p class="drop-text">Arrastra el archivo PDF aquí o <span class="browse-link">examinar</span></p>
                        <span class="file-format-hint">Formato: .pdf (Cédulas frente/dorso por página)</span>
                        <div class="selected-file-info" id="pdfFileInfo"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Barra de Progreso y Acciones -->
        <div class="upload-actions-panel" style="margin-bottom: 24px;">
            <div id="processingIndicator" class="processing-indicator" style="display: none;">
                <div class="processing-header">
                    <div class="spinner"></div>
                    <div class="processing-text">
                        <strong id="processingTitle">Procesando Ficha con Microservicio OCR...</strong>
                        <span id="processingStep">Extrayendo datos de códigos PDF417 y OCR neuronal...</span>
                    </div>
                </div>
                <div class="progress-track">
                    <div id="progressBarFill" class="progress-bar-fill"></div>
                </div>
            </div>

            <button type="submit" id="btnSubmitUpload" class="btn btn-primary btn-lg">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="5 3 19 12 5 21 5 3"></polygon>
                </svg>
                Iniciar Procesamiento y Cruce
            </button>
        </div>
    </form>

    <!-- Lista de Fichas -->
    <div class="card">
        <div class="card-header">
            <h3>Fichas Procesadas</h3>
            <span class="text-muted">Total: {{ $fichas->count() }}</span>
        </div>
        <div class="card-body">
            @if($fichas->isEmpty())
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                    </div>
                    <h4>No hay fichas cargadas todavía</h4>
                    <p>Sube el reporte de inscripciones en Excel y el archivo PDF de cédulas arriba.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Código Ficha</th>
                                <th>Programa de Formación</th>
                                <th>Inscritos</th>
                                <th>Estado</th>
                                <th>Fecha Carga</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($fichas as $f)
                                <tr>
                                    <td><strong>{{ $f->codigo_ficha }}</strong></td>
                                    <td>{{ $f->programa_formacion }}</td>
                                    <td><span class="badge badge-neutral">{{ $f->total_inscritos }}</span></td>
                                    <td>
                                        @php
                                            $badgeClass = match($f->estado) {
                                                'CRUCE_COMPLETADO' => 'badge-success',
                                                'IMPORTADA'        => 'badge-primary',
                                                'PROCESANDO_OCR'   => 'badge-warning',
                                                default            => 'badge-neutral'
                                            };
                                        @endphp
                                        <span class="badge {{ $badgeClass }}">{{ $f->estado }}</span>
                                    </td>
                                    <td>{{ $f->created_at->format('Y-m-d H:i:s') }}</td>
                                    <td style="white-space: nowrap; width: 140px;">
                                        <div class="action-buttons">
                                            <a href="{{ route('cruce.informe', $f->id) }}" class="btn-sm btn-outline">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                                Ver Informe
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/uploader.js') }}"></script>
@endpush
