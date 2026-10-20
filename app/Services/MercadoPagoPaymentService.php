<?php

namespace App\Services;

use MercadoPago\Client\Payment\PaymentClient;

class MercadoPagoPaymentService
{
    public function obtenerPago(string|int $paymentId): object
    {
        return (new PaymentClient())->get($paymentId);
    }
}