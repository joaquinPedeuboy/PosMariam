<?php

namespace App\Enums;

enum OrderShippingStatus: string
{
    case Pendiente = 'pendiente';
    case Preparando = 'preparando';
    case Enviado = 'enviado';
    case Entregado = 'entregado';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Preparando => 'Preparando',
            self::Enviado => 'Enviado',
            self::Entregado => 'Entregado',
        };
    }
}
