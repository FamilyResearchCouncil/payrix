<?php

namespace Frc\Payrix\Models;

class Txn extends Resource
{
    protected static $uri = 'txns';

    public $expand = [
        'payment',
        'fortxn',
        'fromtxn',
        'samePaymentTxns'
    ];

}