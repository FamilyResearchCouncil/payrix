<?php

namespace Frc\Payrix\Models;

class Txn extends Resource
{
    protected static $uri = 'txns';

    public $expand = [
//        'payment' => Payment::class,
        'fortxn'  => Txn::class,
        'fromtxn' => Txn::class,
//        'samePaymentTxns',
    ];

}