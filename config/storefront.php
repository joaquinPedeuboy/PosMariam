<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Reserva de stock del ecommerce
    |--------------------------------------------------------------------------
    */

    'reservation_minutes' => (int) env(
        'ORDER_STOCK_RESERVATION_MINUTES',
        10
    ),
];