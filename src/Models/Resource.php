<?php namespace Frc\Payrix\Models;


use Frc\Payrix\Http\Client;
use Frc\Payrix\Http\PayrixApiException;
use Frc\Payrix\Models\Concerns\HasAttributes;
use Frc\Payrix\Payrix;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\MultipleItemsFoundException;
use Illuminate\Support\Traits\ForwardsCalls;
use \PayrixPHP\Http\Request,
    \PayrixPHP\Http\Response,
    \PayrixPHP\Http\RequestParams,
    \PayrixPHP\Exceptions\InvalidRequest,
    \PayrixPHP\Utilities\Config as Config;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use function PHPUnit\Framework\isNull;

/**
 * @mixin Client
 */
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


    /**
     * @param $id
     * @return static|null
     */
    public function find($id)
    {
        try {
            return $this->get($id);
        } catch (ItemNotFoundException|MultipleItemsFoundException $e) {
            return null;
        }
    }

    public function findOrFail($id)
    {
        try {
            return $this->get($id);
        } catch (ItemNotFoundException $e) {
            throw new ItemNotFoundException("Could not find " . class_basename(static::class) . " with ID '$id'", 404, $e);
        }
    }

    public function first()
    {
        return $this->get()->first();
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
        return static::$uri ?? Payrix::getUriFromClassname(static::class);
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

    public function setAttribute($key, $value)
    {
        if (isset($this->expand[$key])) {
            return $this->expandValue($key, $value);
        }

        // First we will check for the presence of a mutator for the set operation
        // which simply lets the developers tweak the attribute as it is set on
        // this model, such as "json_encoding" a listing of data for storage.
        if ($this->hasSetMutator($key)) {
            return $this->setMutatedAttributeValue($key, $value);
        }

        // If an attribute is listed as a "date", we'll convert it from a DateTime
        // instance into a form proper for storage on the database tables using
        // the connection grammar's date format. We will auto set the values.
        elseif ($value && $this->isDateAttribute($key)) {
            $value = $this->fromDateTime($value);
        }

        if ($this->isClassCastable($key)) {
            $this->setClassCastableAttribute($key, $value);

            return $this;
        }

        if (!is_null($value) && $this->isJsonCastable($key)) {
            $value = $this->castAttributeAsJson($key, $value);
        }

        // If this attribute contains a JSON ->, we'll set the proper value in the
        // attribute's underlying array. This takes care of properly nesting an
        // attribute in the array's value in the case of deeply nested items.
        if (\Str::contains($key, '->')) {
            return $this->fillJsonAttribute($key, $value);
        }

        if (!is_null($value) && $this->isEncryptedCastable($key)) {
            $value = $this->castAttributeAsEncryptedString($key, $value);
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    public function expandValue($key, $value)
    {
        if (is_null($value)) {
            return $this;
        }

        $resource = $this->getExpandResource($key);

        $value = $resource::find($value);

        $this->attributes[$key] = $value;

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

    public function getQueryArgs()
    {
        // To retrieve nested objects and return them as part of the response,
        // use the 'expand' parameter. The parameter name that you specify determines the resources to return.
        // For example, set '?expand[login][]' to return a nested login resource.
        // The expand parameter is available on all GET requests.
        //
        // Insert an empty bracket [] when expanding nested objects in an expanded array.
        // Example: '?expand[orgEntities][][org][]'
        return collect($this->expand)
            ->map(function ($i) {
                $value = \Str::of($i)->explode(".")
                    ->map(function ($i) {
                        return "[$i]";
                    })
                    ->filter()
                    ->join('[]');

                return "expand{$value}[]";
            })
            ->join('&');
    }

    /**
     * @param $key
     * @return Resource|string
     * @throws \Exception
     */
    private function getExpandResource($key)
    {
        if (!class_exists($class = $this->expand[$key])) {
            throw new \Exception("Expand array should use string keys with class values");
        }

        return $class;
    }


}
