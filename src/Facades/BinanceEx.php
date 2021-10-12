<?php

namespace Aoeng\Laravel\Exchange\Facades;

use Aoeng\Laravel\Exchange\Contracts\ExchangeInterface;
use Aoeng\Laravel\Exchange\Contracts\SymbolInterface;
use Aoeng\Laravel\Exchange\Exchanges\BinanceExchange;
use Aoeng\Laravel\Exchange\Symbols\BinanceSymbol;
use Illuminate\Support\Facades\Facade as LaravelFacade;

/**
 * @method static mixed name()
 * @method static mixed symbols()
 * @method static mixed time()
 * @method static BinanceExchange keySecret(mixed $key, mixed|null $secret, $passphrase = null)
 * @method static BinanceSymbol symbol(array $symbol)
 */
class BinanceEx extends LaravelFacade
{
    protected static function getFacadeAccessor()
    {
        return 'binance-ex';
    }
}
