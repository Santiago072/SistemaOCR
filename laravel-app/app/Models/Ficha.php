<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ficha extends Model
{
    use HasFactory;

    protected $table = 'fichas';

    protected $fillable = [
        'codigo_ficha',
        'programa_formacion',
        'total_inscritos',
        'archivo_excel_nombre',
        'archivo_pdf_nombre',
        'estado'
    ];

    public function aspirantes(): HasMany
    {
        return $this->hasMany(AspiranteExcel::class, 'ficha_id');
    }

    public function documentosOcr(): HasMany
    {
        return $this->hasMany(DocumentoPdfOcr::class, 'ficha_id');
    }

    public function cruces(): HasMany
    {
        return $this->hasMany(CruceConciliacion::class, 'ficha_id');
    }

    public function participantesFinales(): HasMany
    {
        return $this->hasMany(ParticipanteFinal::class, 'ficha_id');
    }
}
