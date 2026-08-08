<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Every gateway sits behind PaymentGatewayInterface. Driver "fake" settles
    | invoices locally and is what the demo installation and the test suite use.
    |
    */

    'gateway' => env('PAYMENT_GATEWAY', 'fake'), // fake | midtrans

    'currency' => 'IDR',

    'midtrans' => [
        'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),

        'enabled_payments' => [
            'bca_va', 'bni_va', 'bri_va', 'permata_va', 'other_va',
            'gopay', 'shopeepay', 'qris',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Behaviour
    |--------------------------------------------------------------------------
    */

    'invoice' => [
        // Days a generated term invoice stays payable.
        'due_days' => 30,

        // Allow paying an invoice in instalments.
        'allow_partial' => true,
    ],

];
