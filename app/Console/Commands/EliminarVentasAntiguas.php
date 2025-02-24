<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\Venta;
use Illuminate\Console\Command;

class EliminarVentasAntiguas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ventas:eliminar-antiguas {dias=30}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Eliminar ventas que tienen más de un cierto número de días.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dias = $this->argument('dias'); // Número de días pasados desde la venta para ser eliminada
        $fechaLimite = Carbon::now()->subDays($dias);

        // Eliminar ventas que sean más antiguas que la fecha límite
        $ventasEliminadas = Venta::where('created_at', '<', $fechaLimite)->delete();

        $this->info("Se han eliminado {$ventasEliminadas} ventas anteriores a {$fechaLimite->toDateString()}");
    }
}
