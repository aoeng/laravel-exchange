<?php

namespace Aoeng\Laravel\Exchange;

use Aoeng\Laravel\Exchange\Contracts\ExchangeInterface;
use Aoeng\Laravel\Exchange\Exceptions\ExchangeException;
use Aoeng\Laravel\Exchange\Symbols\BinanceSymbol;
use Aoeng\Laravel\Exchange\Symbols\OkSymbol;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;

class ExchangeManager
{
    protected $config;


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
     * @return BinanceSymbol|OkSymbol
     * @throws ExchangeException
     */
    public function symbol($symbol, $name = null, $key = null, $secret = null, $passphrase = null)
    {
        if ($name) {
            $this->connect($name, $key, $secret, $passphrase);
        }

        if (empty($this->exchange)) {
            $this->connect($this->getDefault(), $key, $secret, $passphrase);
        }

        return $this->exchange->symbol($symbol);
    }

}
