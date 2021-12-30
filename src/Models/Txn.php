<?php

namespace Frc\Payrix\Models;

class Txn extends Resource
{
    protected static $uri = 'txns';

    public $expand = [
//        'payment',
        'payment.bin',
        'merchant',
        'txnResults',
        'txnDatas',
        'txnMetadatas',
        'token',
        'batch',
        'txnRefs',
        'fortxn'  => Txn::class,
        'fromtxn' => Txn::class,
        'samePaymentTxns',
    ];

}