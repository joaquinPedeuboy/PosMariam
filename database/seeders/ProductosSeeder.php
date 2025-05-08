<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Producto;
use League\Csv\Reader;

class ProductosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ruta al archivo CSV
        $file = storage_path('app/public/productosMariam1.csv');

        // Leer el archivo CSV
        $csv = Reader::createFromPath($file, 'r');
        $csv->setHeaderOffset(0); // La primera fila contiene los encabezados

        foreach ($csv as $row) {
            // Crear un nuevo producto en la base de datos
            Producto::create([
                'nombre' => $row['Nombre'], // Columna "Nombre"
                'codigo_barras' => $row['C≤digo art.'], // Columna "Código art."
                'precio' => $row['P. Venta 1'], // Columna "P. Venta 1"
                'departamento_id' => null, // departamento_id es null
                'disponible' => 0, // disponible es 0
            ]);
        }
    }
}