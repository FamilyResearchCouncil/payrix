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
    use ForwardsCalls;

    protected static $uri;

    public $sort = null;
    public $expand = [];
    public $page = 1;
    public $totals = [];

    private $api_connection;
    private $connection_name;

    public function __construct($attributes = [])
    {
        $this->fill($attributes);
    }

    /*******************************************************
     * magic methods
     ******************************************************/

    public function __call($name, $arguments)
    {
        return $this->forwardCallTo($this->newClient(), $name, $arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        $attributes = is_array(reset($arguments))
            ? \Arr::first($arguments)
            : null;

        $instance = new static($attributes ?? []);

        $arguments = isset($attributes)
            ? [$instance->attributesToArray()]
            : $arguments;

        return $instance->$name(...$arguments);
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

    /*******************************************************
     * static methods
     ******************************************************/

    public static function uri()
    {
        return static::$uri ?? (string)\Str::of(class_basename(static::class))->pluralStudly()->camel();
    }

    /**
     * @param $connection
     * @return static
     */
    public static function connection($connection)
    {
        return tap(new static)->setApiConnection($connection);
    }

    /*******************************************************
     * methods
     ******************************************************/
    public function throwsApiErrors()
    {
        return true;
    }

    public function fill($attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    public function refresh()
    {
        return $this->fill(
            $this->newClient()->get($this->id)->getAttributes()
        );
    }

    public function getLogin()
    {
        return Login::connection($this->getConnectionName())->get()->hydrate()->first();
    }

    public function toArray()
    {
        return $this->getAttributes();
    }

    /**
     * @param $connection
     */
    public function setApiConnection($connection)
    {
        $this->api_connection = is_string($connection) ? Payrix::connection($connection) : $connection;
    }

    public function getApiConnection()
    {
        return $this->api_connection ?? Payrix::connection($this->getConnectionName());
    }


    public function getConnectionName()
    {
        return $this->connection_name ?? config('payrix.default_account');
    }

    public function newClient()
    {
        return $this->getApiConnection()->client($this);
    }

    /*******************************************************
     * attribute setters
     ******************************************************/
    /**
     * Mutator for $this->connection_name
     * @param $value
     */
    public function setConnectionNameAttribute($value)
    {
        $this->connection_name = $value;
    }


}
