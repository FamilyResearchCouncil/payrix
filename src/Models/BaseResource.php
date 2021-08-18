<?php namespace Frc\Payrix\Models;


use Frc\Payrix\Models\Concerns\HasAttributes;
use Illuminate\Contracts\Support\Arrayable;
use \PayrixPHP\Http\Request,
    \PayrixPHP\Http\Response,
    \PayrixPHP\Http\RequestParams,
    \PayrixPHP\Exceptions\InvalidRequest,
    \PayrixPHP\Utilities\Config as Config;

class BaseResource extends \PayrixPHP\BaseResource implements Arrayable
{
    use HasAttributes;


    public const CLASS_MAP = [
        'transactions' => 'txns',
    ];

    public $sort = null;
    public $expand = [];
    public $page = 1;
    public $totals = [];

    public function __construct($params = array())
    {
        $params = collect($params);

        $this->requestOptions = new \PayrixPHP\Http\RequestParams(
            $params->pull('sort', $this->sort),
            $params->pull('expand', $this->expand),
            $params->pull('page', $this->page),
            $params->pull('totals', $this->totals)
        );

        $this->fill($params->toArray());

        $name = \Str::of(class_basename(static::class))->plural()->lower()->value();

        $this->resourceName = \Arr::get(self::CLASS_MAP, $name, $name);
    }

    public function get($query = [])
    {
        if (!$this->response) {
            $this->retrieve($query);
        }

        return collect($this->getResponse())->mapInto(static::class);
    }


    protected function getRequestValues($params = array(), $getNested = false)
    {
        $this->fill($params);

        $requestOptions = $this->requestOptions;
        $resourceName = $this->resourceName;
        $response = $this->response;

        $this->requestOptions = null;
        $this->resourceName = null;
        $this->response = null;
        $params = null;

        $requestParams = collect($this->attributes)->mapWithKeys(function ($value, $key) use ($getNested) {
            if (isset($value) && !is_scalar($value) && $getNested) {
                // Process nested arrays/objects as well
                if (is_array($value)) {
                    // Nested array of objects
                    $value = collect($value)->map(function ($subVal, $subKey) use ($key) {
                        if ($subVal instanceof BaseResource) {
                            // Append this object to the request array
                            return $subVal->getRequestValues($this->attributes[$key] ?? [], true);
                        } else if (is_scalar($subVal)) {
                            // Append direct values to this nested array
                            return $subVal;
                        } else if (is_array($subVal)) {
                            // Append this array to the request array
                            return (new BaseResource($subVal))
                                ->getRequestValues($this->attributes[$key] ?? [], true);
                        } else {
                            // Unknown value type detected, throw an exception
                            throw new \PayrixPHP\Exceptions\InvalidRequest("Incorrect nesting structure for request");
                        }
                    })->filter()->toArray();

                } else if ($value instanceof BaseResource) {
                    $value = $value->getRequestValues($this->attributes[$key] ?? [], true);

                } else {
                    // Unknown key detected, throw an exception
                    throw new \PayrixPHP\Exceptions\InvalidRequest("Incorrect nesting structure for request");
                }
            }

            return [$key => $value];
        });

        $this->requestOptions = $requestOptions;
        $this->resourceName = $resourceName;
        $this->response = $response;

        return $requestParams->toArray();
    }

    public function getResponse()
    {
        return optional($this->response)->getResponse() ?? [];
    }

    public function retrieve($params = array())
    {
        $Request = Request::getInstance();

        // Get request values
        $values = $this->getRequestValues($params);

        // Get search params
        $search = $this->_buildSearch($values);

        if ($this->requestOptions) {
            $search .= $this->requestOptions->getSort();
        }

        $search = rtrim($search, "&");

        // Set the headers
        // Content type header
        $headers = array('Content-Type: application/json');
        // Search header
        $headers[] = $search;
        $apiKey = Config::getApiKey();

        $sessionKey = Config::getSessionKey();
        // Auth header
        if ($apiKey) {
            $headers[] = "APIKEY: {$apiKey}";
        } else if ($sessionKey) {
            $headers[] = "SESSIONKEY: {$sessionKey}";
        }
        // Totals header
        $totals = $this->requestOptions->getTotals();
        if ($totals) {
            $headers[] = $totals;
        }
        // Set the url;
        $url = Config::getUrl();
        if (!$url) {
            throw new \PayrixPHP\Exceptions\InvalidRequest("Invalid URL");
        }
        $url .= "/{$this->resourceName}";
        $expand = "";
        if ($this->requestOptions) {
            $expand .= $this->requestOptions->getExpand();
            $page = $this->requestOptions->getPage();
            $url .= "?";
            if ($expand) {
                $url .= $expand . "&";
            }
            if ($page) {
                $url .= $page;
            }
        }
        $url = rtrim($url, "/[& | ?]/");
        $res = $Request->sendHttp(
            'GET',
            $url,
            '',
            $headers
        );
        $this->response = new \Frc\Payrix\Http\Response($res[0], $res[1], get_class($this));
        $success = $this->_validateResponse();
        if ($success) {
            // Increment page
            $this->requestOptions->goNextPage();
        }
        return $success;
    }


    private function _buildSearch($values = array())
    {
        $search = "";
        foreach ($values as $key => $value) {
            if (isset($value)) {
                $search .= "{$key}[equals]={$value}&";
            }
        }
        return "SEARCH: {$search}";
    }

    private function _validateResponse()
    {
        // Get the errors
        if ($this->response->hasErrors()) {
            if (Config::exceptionsEnabled()) {
                throw new \PayrixPHP\Exceptions\ApiErrors('There are errors in the response');
            }
            return false;
        }
        return true;
    }

    public function __set($field, $param)
    {
        if (is_array($param)) {
            $param = collect($param);
        }

        $this->setAttribute($field, $param);
    }

    public function __get($name)
    {
        return $this->getAttribute($name);
    }

    public function fill($attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    public function usesTimestamps()
    {
        return false;
    }

    public function getIncrementing()
    {
        return false;
    }


    public function getAttribute(string $key)
    {
        if (!$key) {
            return;
        }

        if (strpos($key, '.')) {
            return data_get($this->attributes, $key);
        }


        // If the attribute exists in the attribute array or has a "get" mutator we will
        // get the attribute's value. Otherwise, we will proceed as if the developers
        // are asking for a relationship's value. This covers both types of values.
        if (array_key_exists($key, $this->attributes) ||
            array_key_exists($key, $this->casts) ||
            $this->hasGetMutator($key) ||
            $this->isClassCastable($key)) {
            return $this->getAttributeValue($key);
        }

        // Here we will determine if the model base class itself contains this given key
        // since we don't want to treat any of those methods as relationships because
        // they are all intended as helper methods and none of these are relations.
        if (method_exists(self::class, $key)) {
            return;
        }

        return $this->getRelationValue($key);
    }

    public function toArray()
    {
        return $this->getAttributes();
    }

}
