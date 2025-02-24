<?php

namespace App\Http\Controllers;

use App\Models\Imagen;
use App\Models\Producto;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ImagenController extends Controller
{
    public function destroy(Imagen $imagen)
    {
        try {
            // Verificar si el archivo existe y eliminarlo
            if (Storage::disk('public')->exists($imagen->url)) {
                // Eliminar el archivo de la carpeta 'uploads' usando Storage
                Storage::disk('public')->delete($imagen->url);
            }

            $imagen->delete();
            return response()->json(['message' => 'Imagen eliminada correctamente'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar la imagen', 'details' => $e->getMessage()], 500);
        }
    }

}
