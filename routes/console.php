<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Tarea programada para eliminar las ventas antiguas
Schedule::call(function () {
    // Define la fecha límite de 3 meses atrás
    $fechaLimite = Carbon::now()->subMonths(3);

    // Elimina las ventas antiguas de la base de datos
    DB::table('ventas')->where('created_at', '<', $fechaLimite)->delete();
})->monthly(); // La tarea se ejectura mensualmente solo una vez al mes