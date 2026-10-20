<?php

namespace App\Services;

use App\Models\Order;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;

class MercadoPagoService
{
    public function __construct()
    {
        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));
    }

    public function crearPreferencia(Order $order): array
    {
        $storefrontUrl = rtrim(config('app.storefront_url'), '/');

        $items = $order->items->map(fn ($item) => [
            'title' => $item->nombre_producto,
            'quantity' => (int) $item->cantidad,
            'unit_price' => (float) $item->precio_unitario,
            'currency_id' => 'ARS',
        ])->toArray();

        // El envío también forma parte del importe que debe pagar el cliente.
        if ((float) $order->costo_envio > 0) {
            $items[] = [
                'title' => 'Costo de envío',
                'quantity' => 1,
                'unit_price' => (float) $order->costo_envio,
                'currency_id' => 'ARS',
            ];
        }
        
        $client = new PreferenceClient();
        
        try{
            $preference = $client->create([
                'items' => $items,
                'payer' => [
                    'name' => $order->nombre_contacto,
                    'phone' => ['number' => $order->telefono_contacto],
                ],
                'external_reference' => $order->numero,
                'back_urls' => [
                    'success' => $storefrontUrl . '/checkout/pago/success',
                    'failure' => $storefrontUrl . '/checkout/pago/failure',
                    'pending' => $storefrontUrl . '/checkout/pago/pending',
                ],
                'auto_return' => 'approved',
                'notification_url' => config('app.url') . '/api/store/mercadopago/webhook',
            ]);
        }catch (MPApiException $e) {
            \Log::error('Error creando preferencia de Mercado Pago', [
                'status' => $e->getApiResponse()?->getStatusCode(),
                'content' => $e->getApiResponse()?->getContent(),
            ]);
            throw $e;
        }

        return [
            'preference_id' => $preference->id,
            'init_point' => $preference->init_point,
            'sandbox_init_point' => $preference->sandbox_init_point,
        ];
    }
}