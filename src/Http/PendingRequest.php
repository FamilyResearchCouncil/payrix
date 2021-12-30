<?php

namespace Frc\Payrix\Http;

class PendingRequest extends \Illuminate\Http\Client\PendingRequest
{
    public function createClient($handlerStack)
    {
        return new GuzzleClient([
            'handler' => $handlerStack,
            'cookies' => true,
        ]);
    }

}