<?php

namespace Aoeng\Laravel\Exchange;


use Illuminate\Support\ServiceProvider;

class ExchangeServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/exchange.php' => config_path('exchange.php'),
        ], 'laravel-exchange');

    }

    public function register()
    {
        $this->app->singleton('exchange', function ($app) {
            return new ExchangeManager($app);
        });

    }

}
