<?php namespace Frc\Payrix\Models;


use Frc\Payrix\Models\Concerns\HasAttributes;
use Frc\Payrix\Payrix;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Traits\ForwardsCalls;
use \PayrixPHP\Http\Request,
    \PayrixPHP\Http\Response,
    \PayrixPHP\Http\RequestParams,
    \PayrixPHP\Exceptions\InvalidRequest,
    \PayrixPHP\Utilities\Config as Config;

abstract class Resource implements Arrayable
{
    use HasAttributes;

//    use ForwardsCalls;

    protected static $uri;

    public $sort = null;
    public $expand = [];
    public $page = 1;
    public $totals = [];

    public function __construct($attributes = [])
    {
        $this->fill($attributes);
    }

    public static function uri()
    {
        return static::$uri ?? (string)\Str::of(class_basename(static::class))->pluralStudly()->camel();
    }


    /**
     * @param $connection
     * @return \Illuminate\Http\Client\PendingRequest
     */
    public static function on($connection)
    {
        return Payrix::connection($connection)->http();
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


    public function toArray()
    {
        return $this->getAttributes();
    }
}
