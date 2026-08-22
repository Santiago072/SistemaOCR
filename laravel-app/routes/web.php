<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FichaController;
use App\Http\Controllers\InformeController;

Route::get('/', [FichaController::class, 'index'])->name('home');
Route::post('/fichas/procesar', [FichaController::class, 'procesar'])->name('fichas.procesar');
Route::delete('/fichas/{ficha}', [FichaController::class, 'eliminar'])->name('fichas.eliminar');

Route::get('/cruce/informe/{ficha}', [InformeController::class, 'show'])->name('cruce.informe');
Route::post('/cruce/importar/{ficha}', [InformeController::class, 'importarFinal'])->name('cruce.importar');
