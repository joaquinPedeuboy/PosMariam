<?php

namespace Database\Seeders;

use League\Csv\Reader;
use App\Models\Departamento;
use Illuminate\Database\Seeder;

class DepartamentosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ruta al archivo CSV
        $file = storage_path('app/public/DepartamentoMariam1.csv');

        // Leer el archivo CSV
        $csv = Reader::createFromPath($file, 'r');
        $csv->setHeaderOffset(0); // La primera fila contiene los encabezados

        foreach ($csv as $row) {
            // Crear un nuevo producto en la base de datos
            Departamento::create([
                'nombre' => $row['Nombre'], // Columna "Nombre"
            ]);
        }
    }
}
