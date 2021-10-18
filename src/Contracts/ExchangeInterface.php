<?php

namespace Aoeng\Laravel\Exchange\Contracts;


use Aoeng\Laravel\Exchange\Facades\Exchange;

/**
 * @method keySecret(string $key, string $secret, string $passphrase = null)
 * @method balance()
 * @method positions()
 * @method positionSide()
 * @method changePositionSide(string $positionMode = Exchange::POSITION_MODE_DOUBLE)
 * @method transfer($amount, $type = Exchange::TRANSFER_SPOT2SWAP, $asset = 'USDT')
 */
interface ExchangeInterface
{

    /**
     * 交易所名称
     * @return mixed
     */
    public function name();

    /**
     * 交易对
     * @return mixed
     */
    public function symbols();

    public function symbol($symbol);

}
