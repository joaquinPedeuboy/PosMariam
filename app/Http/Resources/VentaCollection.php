<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class VentaCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->transform(function ($venta) {
                return [
                    'id' => $venta->id,
                    'total' => $venta->total,
                    'productos' => $venta->productos->map(function ($producto) {
                        return [
                            'id' => $producto->id,
                            'nombre' => $producto->nombre,
                            'precio' => $producto->precio,
                            'cantidad' => $producto->pivot->cantidad,
                            'vencimiento_id' => $producto->pivot->vencimiento_id,
                            'precio_unitario' => $producto->pivot->precio_unitario,
                        ];
                    }),
                    'created_at' => $venta->created_at->toDateString(),
                ];
            }),
        ];
    }
}
