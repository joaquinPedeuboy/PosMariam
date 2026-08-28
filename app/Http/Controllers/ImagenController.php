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
    public function destroy(Request $request, Imagen $imagen)
    {
        try {
            if (Storage::disk('public')->exists($imagen->url)) {
                Storage::disk('public')->delete($imagen->url);
            }

            $imagen->delete();

            if ($request->header('X-Inertia')) {
                return back()->with('success', 'Imagen eliminada correctamente');
            }

            return response()->json(['message' => 'Imagen eliminada correctamente'], 200);
        } catch (\Exception $e) {
            if ($request->header('X-Inertia')) {
                return back()->with('error', 'Hubo un error al eliminar la imagen');
            }

            return response()->json(['error' => 'Error al eliminar la imagen', 'details' => $e->getMessage()], 500);
        }
    }

}
