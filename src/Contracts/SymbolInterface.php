<?php

namespace Aoeng\Laravel\Exchange\Contracts;

use Aoeng\Laravel\Exchange\Facades\Exchange;

/**
 * @method keySecret(mixed $key, mixed|null $secret)
 * @method maxExchange($positionType)
 * @method changeLever($leverage, $positionType, $positionSide)
 */
interface SymbolInterface
{

    /**
     * K线
     * @return mixed
     */
    public function klins($env, $period, $limit = 500);


    /**
     * 开仓
     * @return mixed
     */
    public function open($positionSide, $volume, $env = Exchange::ENV_SWAP, $price = 0, $rate = 0);

    /**
     * 平仓
     * @return mixed
     */
    public function close($positionSide, $volume, $env = Exchange::ENV_SWAP, $price = 0, $rate = 0);


}
