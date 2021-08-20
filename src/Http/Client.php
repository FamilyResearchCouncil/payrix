<?php

namespace Frc\Payrix\Http;

use Exception;
use Frc\Payrix\Models\Resource;
use Frc\Payrix\Payrix;
use Http;
use Illuminate\Http\Client\PendingRequest;
use Str;

/**
 * @method get(...$args)
 * @method post(...$args)
 * @method put(...$args)
 */
class Client
{
    private Resource $resource;


    public function __construct(Resource $resource)
    {
        $this->resource = $resource;
    }


    /**
     * @param $name
     * @param $arguments
     * @return Response|PendingRequest|Resource
     */
    public function __call($name, $arguments)
    {
        // proxy the pending request for all non-sending method calls
        if (!in_array($name, ['get', 'post', 'put', 'delete'])) {
            return tap($this->buildRequest())->$name(...$arguments);
        }


        $path = is_string(\Arr::first($arguments))
            // remove first element in $arguments if it is a string
            ? Str::of(array_shift($arguments))->explode('/')
            : collect();

        // if the path does not start with the uri of the given class, add it
        if ($path->first() !== $uri = $this->resource->uri()) {
            $path = $path->prepend($uri);
        }

        // normalize
        $path = $path->filter()->join('/');

        // make the api request
        $result = $this->buildRequest()->$name($path, ...$arguments);

        /**
         * The only time an endpoint receives 2 path-parts is when
         * we are looking for a single item/instance using the {id}, e.g. txns/{id}
         */
        $instance_request = Str::of($path)->explode('/')->count() === 2;

        /**
         * finally, we'll handle the response, giving the sole rsource instance
         * if we're looking for one, or the actual resopnse class
         **/
        $response = new Response($result, get_class($this->resource));

        if ($this->resource->throwsApiErrors() && $response->errors()->isNotEmpty()) {
            throw new PayrixApiException($response->errors());
        }

        return $instance_request
            ? $response->hydrate()->sole()
            : $response;
    }


    public function all()
    {
        return $this->get();
    }

    public function paginate(callable $closure)
    {
        do {
            $response = $this->get();

            $continue = $response->json();
        } while ($continue);
    }

    public function create(array $attributes)
    {
        return $this->post($attributes);
    }

    public function update($attributes)
    {
        return $this->put($this->resource->id, $attributes);
    }

    public function delete()
    {
        throw new Exception("Cannot delete resource | Method not implemented");
    }

    public function buildRequest(): PendingRequest
    {
        return Http::baseUrl(config('payrix.base_url'))
            ->asJson()
            ->withHeaders([
                'APIKEY' => $this->resource->getApiConnection()->config('api-key')
            ]);
    }
}