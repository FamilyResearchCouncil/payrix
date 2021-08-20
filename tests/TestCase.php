<?php namespace Frc\Payrix\Tests;

use Dotenv\Dotenv;
use Frc\Payrix\PayrixServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Env;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            PayrixServiceProvider::class
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        if (!$key = \Arr::first(Dotenv::parse(file_get_contents(__DIR__ . '/../.env')))) {
            throw new \Exception("Add your payrix api key to .env to get started testing...");
        }

        config()->set('payrix.accounts.default.api-key', $key);
    }
}