<?php namespace Frc\Payrix\Http;

use Illuminate\Support\Traits\ForwardsCalls;

/**
 * @mixin \Illuminate\Http\Client\Response
 */
class Response
{
    use ForwardsCalls;

    protected \Illuminate\Http\Client\Response $response;
    /**
     * @var null
     */
    private $hydrate_with;

    /**
     * @param \Illuminate\Http\Client\Response $response
     */
    public function __construct(\Illuminate\Http\Client\Response $response, $hydrate_with = null)
    {
        $this->response = $response;
        $this->hydrate_with = $hydrate_with;
    }


    public function __call($name, $arguments)
    {
        return $this->forwardCallTo($this->response, $name, $arguments);
    }

    public function hydrate()
    {
        $data = $this->collect('response.data');

        return isset($this->hydrate_with)
            ? $data->mapInto($this->hydrate_with)
            : $data;
    }

    public function first()
    {
        return $this->hydrate()->first();
    }

    public function errors()
    {
        return collect($this->json('response.errors'))->mapWithKeys(function ($error) {
            $key = \Arr::pull($error, 'field');

            return [$key => $error['msg'] . " ({$error['errorCode']})"];
        });
    }
}