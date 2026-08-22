<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoPdfOcr extends Model
{
    use HasFactory;

    protected $table = 'documentos_pdf_ocr';
    public $timestamps = false;

    protected $fillable = [
        'ficha_id',
        'numero_pagina',
        'tipo_documento',
        'numero_documento',
        'primer_apellido',
        'segundo_apellido',
        'primer_nombre',
        'segundo_nombre',
        'nombre_completo_ocr',
        'genero',
        'fecha_nacimiento',
        'rh',
        'metodo_extraccion',
        'confianza_score',
        'ruta_imagen_recorte',
        'raw_data_json'
    ];

    protected $casts = [
        'confianza_score' => 'float',
        'raw_data_json' => 'array'
    ];

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(Ficha::class, 'ficha_id');
    }
}
