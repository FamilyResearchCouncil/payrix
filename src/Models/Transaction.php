<?php namespace Frc\Payrix\Models;

class Transaction extends Resource
{
    public $expand = [
        'payment',
        'fortxn',
        'fromtxn',
        'samePaymentTxns'
    ];
}
