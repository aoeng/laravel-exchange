<?php

namespace Aoeng\Laravel\Exchange\Contracts;

/**
 * @method keySecret(mixed $key, mixed|null $secret)
 * @method maxExchange($marginType)
 * @method changeLever($leverage, $marginType, $positionSide)
 */
interface SymbolInterface
{

    const ENV_SPOT = 'SPOT';
    const ENV_FUTURE = 'FUTURE';
    const ENV_SWAP = 'SWAP';

    /**
     * K线
     * @return mixed
     */
    public function klins($env, $period, $limit = 500);


    /**
     * 开仓
     * @return mixed
     */
    public function open($positionSide, $volume, $price = 0, $rate = 0);

    /**
     * 平仓
     * @return mixed
     */
    public function close($positionSide, $volume, $price = 0, $rate = 0);


}
