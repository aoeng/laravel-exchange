<?php

namespace Aoeng\Laravel\Exchange;


use Aoeng\Laravel\Exchange\Exchanges\BinanceExchange;
use Aoeng\Laravel\Exchange\Exchanges\OkExchange;
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

        $this->app->singleton('binance-ex', function ($app) {
            return new BinanceExchange($app['config']['exchange']['exchanges']['binance']);
        });

        $this->app->singleton('ok-ex', function ($app) {
            return new OkExchange($app['config']['exchange']['exchanges']['ok']);
        });

    }

}
