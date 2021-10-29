<?php namespace Frc\Payrix;

use Illuminate\Support\ServiceProvider;

class PayrixServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/payrix.php', 'payrix');

    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton('payrix', function () {
            return new Payrix();
        });

        foreach (config('payrix.accounts') as $key => $config) {
            $this->app->singleton("payrix.$key", function () use ($key) {
                return new Payrix($key);
            });
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/payrix.php' => config_path('payrix.php'),
            ], 'payrix');
        }
    }
}
