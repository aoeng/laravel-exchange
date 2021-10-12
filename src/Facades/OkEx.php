<?php

namespace Aoeng\Laravel\Exchange\Facades;

use Aoeng\Laravel\Exchange\Exchanges\OkExchange;
use Aoeng\Laravel\Exchange\Symbols\OkSymbol;
use Illuminate\Support\Facades\Facade as LaravelFacade;

/**
 * @method static mixed name()
 * @method static mixed symbols()
 * @method static mixed time()
 * @method static OkExchange keySecret(mixed $key, mixed|null $secret, $passphrase = null)
 * @method static OkSymbol symbol(array $symbol)
 */
class OkEx extends LaravelFacade
{
    protected static function getFacadeAccessor()
    {
        return 'ok-ex';
    }
}
