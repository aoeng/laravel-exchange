<?php

namespace Aoeng\Laravel\Exchange\Facades;

use Aoeng\Laravel\Exchange\Contracts\ExchangeInterface;
use Aoeng\Laravel\Exchange\Contracts\SymbolInterface;
use Illuminate\Support\Facades\Facade as LaravelFacade;

/**
 * @method static ExchangeInterface connect($name, $key = null, $secret = null, $passphrase = null)
 * @method static SymbolInterface symbol($symbol, $exchange, $key = null, $secret = null, $passphrase = null)
 */
class Exchange extends LaravelFacade
{
    protected static function getFacadeAccessor()
    {
        return 'exchange';
    }
}
