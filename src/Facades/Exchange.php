<?php

namespace Aoeng\Laravel\Exchange\Facades;

use Aoeng\Laravel\Exchange\Exchanges\BinanceExchange;
use Aoeng\Laravel\Exchange\Exchanges\OkExchange;
use Aoeng\Laravel\Exchange\Symbols\BinanceSymbol;
use Aoeng\Laravel\Exchange\Symbols\OkSymbol;
use Illuminate\Support\Facades\Facade as LaravelFacade;

/**
 * @method static BinanceExchange|OkExchange connect($name, $key = null, $secret = null, $passphrase = null)
 * @method static BinanceSymbol|OkSymbol symbol($symbol, $exchange, $key = null, $secret = null, $passphrase = null)
 */
class Exchange extends LaravelFacade
{
    const ENV_SPOT = 'SPOT';
    const ENV_SWAP = 'SWAP';

    const EXCHANGE_BINANCE = 'binance';
    const EXCHANGE_OKEX = 'ok';

    const TRANSFER_SPOT2SWAP = 1;
    const TRANSFER_SWAP2SPOT = -1;

    const POSITION_MODE_DOUBLE = 2;
    const POSITION_MODE_SINGLE = 1;

    const POSITION_SIDE_BOTH = 0;
    const POSITION_SIDE_LONG = 1;
    const POSITION_SIDE_SHORT = -1;

    const POSITION_TYPE_CASH = 0;
    const POSITION_TYPE_CROSSED = 1;
    const POSITION_TYPE_ISOLATED = 2;

    const ORDER_SIDE_BUY = 1;
    const ORDER_SIDE_SELL = -1;

    const ORDER_TYPE_MARKET = 1;
    const ORDER_TYPE_LIMIT = 2;
    const ORDER_TYPE_TRAILING = 3;

    const ORDER_QUANTITY_TYPE_BASE = 1;
    const ORDER_QUANTITY_TYPE_QUITE = 2;

    const MARGIN_CHANGE_ADD = 1;
    const MARGIN_CHANGE_SUB = -1;


    protected static function getFacadeAccessor()
    {
        return 'exchange';
    }
}
