<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendientePago = 'pendiente_pago';
    case Pagado = 'pagado';
    case Fallido = 'fallido';
    case Cancelado = 'cancelado';
}
