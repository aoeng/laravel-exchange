<?php

namespace Aoeng\Laravel\Exchange\Contracts;


/**
 * @method keySecret(mixed $key, mixed|null $secret)
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
