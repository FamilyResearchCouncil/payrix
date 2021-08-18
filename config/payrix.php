<?php

return [
    'base_url' => env('PAYRIX_URL', 'https://api.payrix.com'),

    'default_account' => 'default',
    'accounts'        => [
        'default' => [
            'api-key' => env('PAYRIX_PRIVATE_KEY')
        ]
    ],

    'resources' => [
        // override class payrix class mapping here
        'txns'         => Frc\Payrix\Models\Transaction::class,
        'transactions' => Frc\Payrix\Models\Transaction::class
    ]
];
