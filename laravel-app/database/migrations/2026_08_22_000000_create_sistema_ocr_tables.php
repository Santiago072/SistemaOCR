<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabla de Fichas de Formacion
        Schema::create('fichas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_ficha', 50)->unique();
            $table->string('programa_formacion', 255);
            $table->integer('total_inscritos')->default(0);
            $table->string('archivo_excel_nombre', 255)->nullable();
            $table->string('archivo_pdf_nombre', 255)->nullable();
            $table->enum('estado', ['CARGADA', 'PROCESANDO_OCR', 'CRUCE_COMPLETADO', 'IMPORTADA'])->default('CARGADA');
            $table->timestamps();
        });

        // 2. Tabla de Aspirantes cargados desde el Excel
        Schema::create('aspirantes_excel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ficha_id')->constrained('fichas')->onDelete('cascade');
            $table->string('tipo_documento', 10);
            $table->string('numero_documento', 30);
            $table->string('nombre_completo', 255);
            $table->string('estado_inscripcion', 100)->default('Preinscrito');
            $table->timestamp('created_at')->useCurrent();

            $table->index('numero_documento');
            $table->index('ficha_id');
        });

        // 3. Tabla de Documentos extraidos del PDF mediante OCR / PDF417
        Schema::create('documentos_pdf_ocr', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ficha_id')->constrained('fichas')->onDelete('cascade');
            $table->integer('numero_pagina');
            $table->string('tipo_documento', 10)->default('CC');
            $table->string('numero_documento', 30)->nullable();
            $table->string('primer_apellido', 100)->nullable();
            $table->string('segundo_apellido', 100)->nullable();
            $table->string('primer_nombre', 100)->nullable();
            $table->string('segundo_nombre', 100)->nullable();
            $table->string('nombre_completo_ocr', 255)->nullable();
            $table->string('genero', 10)->nullable();
            $table->string('fecha_nacimiento', 30)->nullable();
            $table->string('rh', 5)->nullable();
            $table->string('metodo_extraccion', 50)->default('PDF417');
            $table->decimal('confianza_score', 5, 2)->default(100.00);
            $table->string('ruta_imagen_recorte', 255)->nullable();
            $table->text('raw_data_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('numero_documento');
            $table->index('ficha_id');
        });

        // 4. Tabla del Informe de Cruce y Conciliacion
        Schema::create('cruce_conciliacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ficha_id')->constrained('fichas')->onDelete('cascade');
            $table->foreignId('aspirante_excel_id')->nullable()->constrained('aspirantes_excel')->onDelete('set null');
            $table->foreignId('documento_pdf_id')->nullable()->constrained('documentos_pdf_ocr')->onDelete('set null');
            $table->enum('estado_cruce', ['CONCILIADO', 'DIFERENCIA_NOMBRE', 'FALTANTE_PDF', 'SOBRANTE_PDF', 'ILEGIBLE']);
            $table->decimal('similitud_nombres_porcentaje', 5, 2)->default(0.00);
            $table->text('observaciones')->nullable();
            $table->boolean('validado_manualmente')->default(false);
            $table->timestamp('fecha_validacion')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('estado_cruce');
            $table->index('ficha_id');
        });

        // 5. Tabla Final de Participantes Importados / Aprobados
        Schema::create('participantes_finales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ficha_id')->constrained('fichas')->onDelete('cascade');
            $table->string('tipo_documento', 10);
            $table->string('numero_documento', 30);
            $table->string('nombres', 150);
            $table->string('apellidos', 150);
            $table->string('nombre_completo', 255);
            $table->string('genero', 10)->nullable();
            $table->string('fecha_nacimiento', 30)->nullable();
            $table->string('rh', 5)->nullable();
            $table->string('estado_inscripcion', 100)->default('Matriculado / Validado');
            $table->enum('origen_validacion', ['AUTOMATICO_OCR', 'MANUAL_SUPERVISOR'])->default('AUTOMATICO_OCR');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['ficha_id', 'numero_documento'], 'uk_ficha_documento');
            $table->index('numero_documento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participantes_finales');
        Schema::dropIfExists('cruce_conciliacion');
        Schema::dropIfExists('documentos_pdf_ocr');
        Schema::dropIfExists('aspirantes_excel');
        Schema::dropIfExists('fichas');
    }
};
