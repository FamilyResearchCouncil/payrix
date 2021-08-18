<?php namespace Frc\Payrix\Models;

class Transaction extends BaseResource
{
    public $expand = [
        'payment',
        'fortxn',
        'fromtxn',
        'samePaymentTxns'
    ];
}
