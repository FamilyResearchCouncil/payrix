<?php

namespace Frc\Payrix\Http;

use Illuminate\Support\Collection;
use Throwable;

class PayrixApiException extends \Exception
{
    public function __construct(Collection $errors, $code = 0, Throwable $previous = null)
    {
        $message = $errors
            ->prepend(null, "Errors")
            ->map(fn($v, $k) => "$k: $v")->join("\n\t");

        parent::__construct($message, $code, $previous);
    }

}