<?php namespace Frc\Payrix;

use Frc\Payrix\Exceptions\ResourceNotFoundException;
use Frc\Payrix\Http\Client;
use Frc\Payrix\Models\Resource;
use Frc\Payrix\Models\Transaction;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Stringable;
use Str;

/**
 * @method Transaction transactions
 *
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
        // if this is the base resource, determine with endpoint we're using
        if (self::class === static::class) {

            preg_match("/^(?'verb'get|post|put|patch|delete)(?'method'\w*)$/", $method, $matches);

            $matches = collect($matches);

            $method = (string)Str::of($matches->get('method', $method))->camel();

            // the method being called should correspond to one of the payrix resources
            if (!$class = static::getClassFromUri($method)) {
                throw new ResourceNotFoundException("Could not determine the endpoint to use for: '$method'");
            }

            $resource = $class::connection($this);

            return ($verb = $matches->get('verb'))
                // call the http method on the given resource
                ? $resource->$verb(...$args)
                // return the requested resource
                : $resource;
        }

        // this is an endpoint resource
        return $this->$method(...$args);
    }

    public static function __callStatic(string $name, array $arguments)
    {
        return (new static())->$name(...$arguments);
    }


    public function client($resource)
    {
        return new Client($resource);
    }

    /**
     *
     * @return Collection ['$uri' => '$class']
     */
    private static function getResrouceClasses(): Collection
    {
        return collect(scandir(__DIR__ . "/Models"))->mapInto(Stringable::class)
            // exclude dir refs
            ->filter(fn($v) => !$v->is(['.', '..']))
            // make full class name - prepend namespace and remove suffix
            ->map(fn($v) => (string)$v->replaceLast('.php', '')->prepend('Frc\Payrix\Models\\'))
            // exclude non-classes (e.g. directories)
            ->filter(fn($v) => class_exists($v))
            // set up uri as key (txns => Txn)
            ->mapWithKeys(fn($class) => [$class::uri() => $class])
            // override with custom mappings from config file
            ->merge(collect(config('payrix.resources')));
    }

    public static function getUriFromClassname($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        return (string)\Str::of(class_basename($class))
            ->pluralStudly()
            ->camel();
    }

    /**
     * @return Resource|string
     */
    public static function getClassFromUri($path)
    {
        // pull in list of php class files from /Models dir
        return static::getResrouceClasses()
            // get the first entry where the current path matches the uri (key)
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

    public function resource(string $uri): Resource
    {
        if (class_exists($uri)) {
            $uri = static::getUriFromClassname($uri);
        }

        return $this->$uri();
    }
}
