<?php namespace Frc\Payrix;

use Frc\Payrix\Exceptions\ResourceNotFoundException;
use Frc\Payrix\Http\Client;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Stringable;

/**
 * Class Payrix
 * @package Frc\Payrix
 * @mixin PendingRequest
 */
class Payrix
{
    protected string $account_name;
    /**
     * @var mixed
     */
    private $path;

    public function __construct($account_name = 'default')
    {
        $this->setConnection($account_name);
    }

    /**
     * @param $account_name
     * @return Payrix
     */
    public static function connection($account_name)
    {
        $instance = new static;

        $instance->setConnection($account_name);

        return $instance;
    }

    public function __call($method, $args)
    {
        // pull the first argument into a collection
        $path = \Str::of(array_shift($args))->explode("/")->filter();

        /** @var \Frc\Payrix\Models\Resource $class */
        if (!$class = static::getResourceClass($path->first())) {
            throw new ResourceNotFoundException("Could not determine the endpoint to use for the given path: '{$path->join("/")}'");
        }

        // call the http method on the given class using the current account connection
        return $class::connection($this)->$method($path->join("/"), ...$args);

    }

    public static function __callStatic(string $name, array $arguments)
    {
        return (new static())->$name(...$arguments);
    }


    public function client($resource)
    {
        return new Client($resource);
    }


    public static function getResourceClass($path)
    {
        return collect(scandir(__DIR__ . "/Models"))->mapInto(Stringable::class)
            // exclude dir refs
            ->filter(fn($v) => !$v->is(['.', '..']))
            // make full class nae
            ->map(fn($v) => (string)$v->replaceLast('.php', '')->prepend('Frc\Payrix\Models\\'))
            // remove directories
            ->filter(fn($v) => class_exists($v))
            // set up uri as key
            ->mapWithKeys(fn($class) => [$class::uri() => $class])
            // override from config file
            ->merge(collect(config('payrix.resources')))
            // get the first entry matching the current path
            ->first(fn($v, $k) => $path === $k);
    }

    public function setConnection($connection)
    {
        $this->account_name = $connection;
    }

    public function config($key = null)
    {
        if (is_null($key)) {
            return config("payrix.accounts.$this->account_name");
        }

        return config("payrix.accounts.$this->account_name.$key");
    }
}
