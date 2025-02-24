<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ProductoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $datos = [];

        // Listado de categorías y nombres para productos
        $nombres = [
            'Champú Anticaspa',
            'Champú Hidratante',
            'Acondicionador Reparador',
            'Acondicionador Suavizante',
            'Detergente Líquido Multiusos',
            'Jabón Líquido para Manos',
            'Limpia Vidrios Azul',
            'Esponjas de Cocina Pack 6',
            'Toallas de Papel Pack 2',
            'Desodorante en Spray',
            'Desodorante Roll-On',
        ];

        for ($i = 1; $i <= 100; $i++) {
            $nombre = $nombres[array_rand($nombres)]; // Seleccionar un nombre al azar
            $codigo_barras = rand(100000000000, 999999999999); // Generar un código de barras único
            $precio = rand(15, 100) . '.00'; // Precio en múltiplos de 1, formateado como "XX.00"

            $datos[] = [
                'nombre' => $nombre,
                'codigo_barras' => $codigo_barras,
                'precio' => $precio,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        DB::table('productos')->insert($datos);
    }
}
