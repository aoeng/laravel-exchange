<?php

namespace Aoeng\Laravel\Exchange;

use Aoeng\Laravel\Exchange\Contracts\ExchangeInterface;
use Aoeng\Laravel\Exchange\Exceptions\ExchangeException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;

class ExchangeManager
{
    protected $config;

    const EXCHANGE_BINANCE = 'binance';
    const EXCHANGE_OKEX = 'ok';

    /**
     * @var ExchangeInterface
     */
    protected $exchange;

    protected $symbol;

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->config = $app['config']['exchange'];

    }

    public function getDefault()
    {
        return $this->config['default'] ?? 'binance';
    }


    /**
     * @throws ExchangeException
     */
    public function connect($name, $key = null, $secret = null, $passphrase = null)
    {
        $className = '\Aoeng\Laravel\Exchange\Exchanges\\' . Str::ucfirst($name) . 'Exchange';

        if (!class_exists($className)) {
            throw new ExchangeException('Class not Fund!', 301);
        }

        $this->exchange = new $className($this->config['exchanges'][$name] ?? []);

        $key && $this->exchange->keySecret($key, $secret, $passphrase);

        return $this->exchange;
    }

    /**
     * @throws ExchangeException
     */
    public function symbol($symbol, $name = null, $key = null, $secret = null, $passphrase = null)
    {
        $this->connect($name ?? $this->getDefault(), $key, $secret, $passphrase);

        return $this->exchange->symbol($symbol);
    }

}
