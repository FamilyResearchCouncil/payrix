<?php

namespace Frc\Payrix\Http;

use Exception;
use Frc\Payrix\Models\Resource;
use Frc\Payrix\Payrix;
use Http;
use Illuminate\Http\Client\PendingRequest;
use Str;
use function RingCentral\Psr7\parse_query;

/**
 * @method get(string $path = null, array $data = [])
 * @method post(string $path = null, array $data = [])
 * @method put(string $path = null, array $data = [])
 */
class Client
{
    private Resource $resource;
    private array $query = [];
    private $path;
    private $response;

    public function path(string $path)
    {
        $this->path = $path;

        return $this;
    }

    public function appendPath(string $path)
    {
        $this->path .= $path;

        return $this;
    }

    public function query(array $query = [])
    {
        $this->query = array_merge($this->query, $query);

        return $this;
    }

    public function newQuery(array $query = [])
    {
        $this->query = $query;

        return $this;
    }

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

        $this->path(array_shift($arguments) ?? '');
        $this->query(array_shift($arguments) ?? []);

        // make the api request
        $this->response = $this->buildRequest()->$name($this->getPath(), $this->getQuery());

        /**
         * The only time an endpoint receives 2 path-parts is when
         * we are looking for a single item/instance using the id, e.g. txns/{id}
         */
        $instance_request = Str::of($this->getPath())->explode('/')->count() === 2;

        /**
         * finally, we'll handle the response, giving the sole rsource instance
         * if we're looking for one, or the actual resopnse class
         **/
        $response = tap(new Response($this->response, get_class($this->resource)), function ($response) {

            if ($this->resource->throwsApiErrors() && $response->errors()->isNotEmpty()) {
                throw new PayrixApiException($response->errors());
            }

        })->hydrate();

        return $instance_request
            ? $response->sole()
            : $response;
    }


    public function all()
    {
        return $this->get();
    }

    public function paginate(callable $closure)
    {
        $this->query = [
            'page' => [
                'number' => 0
            ]
        ];

        do {

            $this->incrementPageNumber();

            $data = $this->get($this->path, $this->query);
            $halt = $closure($data, $this->response) === false;
            $has_more = $this->response->json('response.details.page.hasMore');

            dump(compact('halt', 'has_more'));

        } while (!$halt && $has_more);
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

    private function incrementPageNumber()
    {
        $number = data_get($this->query, 'page.number');

        data_set($this->query, 'page.number', $number + 1);

        return $this;
    }

    private function getQuery()
    {
        return array_merge($this->query, parse_query($this->resourceQueryArgs()));
    }

    private function getPath()
    {
        $path = Str::of($this->path)->explode('/');

        // if the path does not start with the uri of the given resource, prepend it
        if ($path->first() !== ($uri = $this->resource->uri())) {
            $path = $path->prepend($uri);
        }

        $path = Str::of($path->filter()->join("/"));

        return "$path";
    }

    private function resourceQueryArgs()
    {
        return $this->resource->getQueryArgs();
    }
}