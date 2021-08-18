<?php namespace Frc\Payrix;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use PayrixPHP\accounts;
use PayrixPHP\BaseResource;
use PayrixPHP\Exceptions\ApiErrors;
use PayrixPHP\Http\Response;
use PayrixPHP\txns;
use PayrixPHP\Utilities\Config;

/**
 * @method transactions($args = null)
 * @method disbursements($id = null)
 * @method payouts($id = null)
 * @method terminalTxns(array $array)
 *
 * Class Payrix
 * @package Frc\Payrix
 * @mixin BaseResource
 */
class Payrix
{
    protected string $account_name;
    protected Collection $config;

    public function __construct($account_name = 'default')
    {
        $this->account_name = $account_name;
        $this->config = collect(config("payrix.accounts.$account_name"));

        Config::setApiKey($this->config->get('api-key'));
    }


    public function __call($name, $args)
    {
        $object = $this->getPayrixObject($name);

        try {
            \Log::channel('api')->info("payrix/$name", $args);
            $response = $object->retrieve(...$args);
        } catch (ApiErrors $e) {

            $errors = json_encode([
                'errors' => $object->getErrors(),
            ], JSON_PRETTY_PRINT);

            throw new ApiErrors($errors);

        }

        return $object->getResponse();
    }

    public function getPayrixObject($object_name): BaseResource
    {
        $class_override = (string)\Str::of($object_name)->singular()->title()->prepend('\Frc\Payrix\Models\\');
        if (class_exists($class_override)) {
            return new $class_override();
        }

        $name = Arr::get(\Frc\Payrix\Models\BaseResource::CLASS_MAP, $object_name, $object_name);

        $class = "\PayrixPHP\\$name";

        return new $class();
    }
}
