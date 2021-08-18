<?php namespace Frc\Payrix\Http;

class Response extends \PayrixPHP\Http\Response
{
    public function getResponse()
    {
        return collect(data_get($this->response, 'response.data', []))
            ->mapInto($this->class);
    }

}
