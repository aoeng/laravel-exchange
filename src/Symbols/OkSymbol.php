<?php

namespace Aoeng\Laravel\Exchange\Symbols;

use Aoeng\Laravel\Exchange\Contracts\SymbolInterface;
use Aoeng\Laravel\Exchange\Exceptions\ExchangeException;
use Aoeng\Laravel\Exchange\Requests\BinanceRequestTrait;
use Aoeng\Laravel\Exchange\Requests\OkRequestTrait;
use Aoeng\Laravel\Exchange\Traits\SymbolTrait;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;

class OkSymbol implements SymbolInterface
{
    use SymbolTrait, OkRequestTrait;


    const SIDE_SELL = 'sell';
    const SIDE_BUY = 'buy';


    const POSITION_SIDE_SPOT = 'SPOT';
    const POSITION_SIDE_BOTH = 'net';
    const POSITION_SIDE_LONG = 'long';
    const POSITION_SIDE_SHORT = 'short';

    const ORDER_TYPE_LIMIT = 'limit';
    const ORDER_TYPE_MARKET = 'market';
    const ORDER_TYPE_MAKER = 'post_only';
    const ORDER_TYPE_FOK = 'fok';
    const ORDER_TYPE_IOC = 'ioc';
    const ORDER_TYPE_MARKET_IOC = 'optimal_limit_ioc';

    const ORDER_CREATE_TYPE_N = 'n';
    const ORDER_CREATE_TYPE_S = 's';

    const QUANTITY_TYPE_BASE = 'base_ccy';
    const QUANTITY_TYPE_QUITE = 'quote_ccy';

    const MARGIN_TYPE_ISOLATED = 'isolated';
    const MARGIN_TYPE_CROSSED = 'cross';
    const MARGIN_TYPE_CASH = 'cash';

    const ORDER_OPEN_LONG = 1;
    const ORDER_OPEN_SHORT = 2;
    const ORDER_CLOSE_LONG = 3;
    const ORDER_CLOSE_SHORT = 4;

    const MARGIN_ADD = 'add';
    const MARGIN_SUB = 'reduce';


    public function symbol($symbol = [])
    {
        if (!isset($symbol['symbol'])) {
            throw new ExchangeException('交易对[symbol]不存在');
        }

        foreach ($symbol as $key => $value) {
            $key = Str::camel($key);
            $this->$key = $value;
        }

        if (!Str::contains($this->symbol, '-')) {
            $this->symbol = Str::replace('USDT', '-USDT', $this->symbol);
        }

        if (!isset($symbol['contractSymbol']) || !isset($symbol['contract_symbol']) || !Str::contains($this->contractSymbol, '-SWAP')) {
            $this->contractSymbol = $this->symbol . '-SWAP';
        }

        return $this;
    }

    /**
     * @throws GuzzleException
     */
    public function klins($env, $period, $limit = 100, $before = 0, $after = 0)
    {
        $this->path = '/api/v5/market/candles';

        $this->body = array_merge([
            'instId' => $env == self::ENV_SPOT ? $this->symbol : $this->contractSymbol,
            'bar'    => $this->formatPeriod($period),
            'limit'  => $limit
        ], array_filter(compact('before', 'after')));

        return $this->send();
    }


    public function changeLever($leverage, $marginType = self::MARGIN_TYPE_CROSSED, $positionSide = self::POSITION_SIDE_BOTH)
    {
        $this->method = 'POST';
        $this->path = '/api/v5/account/set-leverage';
        $this->body = ['instId' => $this->contractSymbol, 'lever' => $leverage, 'mgnMode' => $marginType, 'posSide' => $positionSide];

        $result = $this->send(false);

        return $this->response($result[0]);
    }

    public function maxExchange($marginType = self::MARGIN_TYPE_CROSSED)
    {
        $this->method = 'GET';
        $this->path = '/api/v5/account/max-size';
        $this->body = ['instId' => $this->contractSymbol, 'tdMode' => $marginType];

        return $this->send();
    }


    /**
     * 调整逐仓保证金
     * @param $amount
     * @param string $type [调整方向 1: 增加逐仓保证金，2: 减少逐仓保证金]
     * @param string $positionSide ['BOTH'] 双向持仓必填['LONG','SHORT']
     * @return array|mixed
     * @throws GuzzleException
     */
    public function changeMargin($amount, string $type = self::MARGIN_ADD, string $positionSide = self::POSITION_SIDE_BOTH)
    {
        $this->method = 'POST';
        $this->path = '/api/v5/account/position/margin-balance';
        $this->body = [
            'instId'  => $this->contractSymbol,
            'amt'     => $amount,
            'type'    => $type,
            'posSide' => $positionSide
        ];

        return $this->send();
    }

    public function createFutureOrder($quantity, $positionSide = self::POSITION_SIDE_BOTH, $side = self::SIDE_BUY, $mode = self::MARGIN_TYPE_CASH, $type = self::ORDER_TYPE_MARKET, $price = null, $newClientOrderId = null)
    {
        $this->method = 'POST';
        $this->path = '/api/v5/trade/order';
        $this->body = array_merge(['instId' => $this->contractSymbol], array_filter([
            'tdMode'  => $mode,
            'clOrdId' => $newClientOrderId,
            'side'    => $side,
            'posSide' => $positionSide,
            'ordType' => $type,
            'sz'      => $quantity,
            'px'      => $price,
        ]));

        return $this->send();
    }

    public function createOrder($quantity, $side = self::SIDE_BUY, $type = self::ORDER_TYPE_MARKET, $price = null, $quantityType = self::QUANTITY_TYPE_BASE, $newClientOrderId = null)
    {
        $this->method = 'POST';
        $this->path = '/api/v5/trade/order';
        $this->body = array_merge(['instId' => $this->contractSymbol], array_filter([
            'tdMode'  => self::MARGIN_TYPE_CASH,
            'clOrdId' => $newClientOrderId,
            'side'    => $side,
            'ordType' => $type,
            'sz'      => $quantity,
            'px'      => $price,
            'tgtCcy'  => $quantityType,
        ]));
        return $this->send();
    }

    public function createFutureTrailingOrder($quantity, $activationPrice = null, $callbackRate = 0.1, $orderType = self::ORDER_OPEN_LONG)
    {
        $this->method = 'POST';
        $this->path = '/api/swap/v3/order_algo';
        $this->body = [
            'instrument_id' => $this->contractSymbol,
            'type'          => $orderType,
            'order_type'    => 2,
            'size'          => $quantity,
            'callback_rate' => $callbackRate,
            'trigger_price' => $activationPrice,
        ];

        return $this->send();
    }

    public function createTrailingOrder($quantity, $side = self::SIDE_BUY, $activationPrice = null, $callbackRate = 0.1)
    {
        $this->method = 'POST';
        $this->path = '/api/swap/v3/order_algo';
        $this->body = [
            'instrument_id' => $this->symbol,
            'mode'          => 1,
            'order_type'    => 2,
            'size'          => $quantity,
            'side'          => $side,
            'callback_rate' => $callbackRate,
            'trigger_price' => $activationPrice,
        ];

        return $this->send();
    }


    public function open($positionSide, $volume, $price = 0, $rate = 0)
    {
        if ($positionSide == self::POSITION_SIDE_SPOT) {
            if ($rate == 0) {
                return $this->createOrder($volume, self::SIDE_BUY, $price == 0 ? self::ORDER_TYPE_MARKET : self::ORDER_TYPE_LIMIT, $price);
            }

            return $this->createTrailingOrder($volume, self::SIDE_BUY, $price, $rate);
        }

        if ($rate == 0) {
            return $this->createFutureOrder($volume, $positionSide, $positionSide == self::POSITION_SIDE_SHORT ? self::SIDE_SELL : self::SIDE_BUY, self::MARGIN_TYPE_CROSSED, $price == 0 ? self::ORDER_TYPE_MARKET : self::ORDER_TYPE_LIMIT, $price);
        }


        return $this->createFutureTrailingOrder($volume, $price, $rate, $positionSide == self::POSITION_SIDE_LONG ? self::ORDER_OPEN_LONG : self::ORDER_OPEN_SHORT);
    }

    public function close($positionSide, $volume, $price = 0, $rate = 0)
    {

        if ($positionSide == self::POSITION_SIDE_SPOT) {
            if (floatval($rate) == 0) {
                return $this->createOrder($volume, self::SIDE_SELL, $price == 0 ? self::ORDER_TYPE_MARKET : self::ORDER_TYPE_LIMIT, $price);
            }

            return $this->createTrailingOrder($volume, self::SIDE_SELL, $price, $rate);
        }

        if (floatval($rate) == 0) {
            return $this->createFutureOrder($volume, $positionSide, $positionSide == self::POSITION_SIDE_SHORT ? self::SIDE_BUY : self::SIDE_SELL, self::MARGIN_TYPE_CROSSED, $price == 0 ? self::ORDER_TYPE_MARKET : self::ORDER_TYPE_LIMIT, $price);
        }


        return $this->createFutureTrailingOrder($volume, $price, $rate, $positionSide == self::POSITION_SIDE_LONG ? self::ORDER_CLOSE_LONG : self::ORDER_CLOSE_SHORT);
    }

    public function cancelOrder($env, $orderId, $origClientOrderId = 0, $type = self::ORDER_CREATE_TYPE_N)
    {
        $this->method = 'POST';
        if ($type == self::ORDER_CREATE_TYPE_N) {
            $this->path = '/api/v5/trade/cancel-order';
            $this->body = array_merge(['instId' => $env == self::ENV_SPOT ? $this->symbol : $this->contractSymbol], array_filter([
                'ordId'   => $orderId,
                'clOrdId' => $origClientOrderId
            ]));
        } else {
            if ($env == self::ENV_SPOT) {
                $this->path = '/api/spot/v3/cancel_batch_algos';
                $this->body = array_merge(['instrument_id' => $this->symbol], array_filter([
                    'algo_ids'   => [$orderId],
                    'order_type' => 2
                ]));
            } else {
                $this->path = '/api/swap/v3/cancel_algos';
                $this->body = array_merge(['instrument_id' => $this->contractSymbol], array_filter([
                    'algo_ids'   => [$orderId],
                    'order_type' => 2
                ]));
            }
        }

        return $this->send();
    }


    public function searchOrder($env, $orderId, $origClientOrderId = 0)
    {
        $this->method = 'GET';
        $this->path = '/api/v5/trade/order';
        $this->body = array_merge(['instId' => $env == self::ENV_SPOT ? $this->symbol : $this->contractSymbol], array_filter([
            'ordId'   => $orderId,
            'clOrdId' => $origClientOrderId
        ]));

        return $this->send();
    }


    public function transactionRecord($env, $limit = 500, $fromId = 0)
    {
        $this->method = 'GET';
        $this->path = '/api/v5/trade/fills';
        $this->body = array_merge(['instId' => $env == self::ENV_SPOT ? $this->symbol : $this->contractSymbol], array_filter([
            'instType' => $env,
            'before'   => $fromId,
            'limit'    => $limit
        ]));

        return $this->send();
    }


    public function pendingOrderRecord($env, $limit = 500, $orderId = 0)
    {
        $this->method = 'GET';
        $this->path = '/api/v5/trade/orders-pending';
        $this->body = array_merge(['instId' => $env == self::ENV_SPOT ? $this->symbol : $this->contractSymbol], array_filter([
            'before' => $orderId,
            'limit'  => $limit
        ]));

        return $this->send();
    }

    public function historyOrderRecord($env, $limit = 500, $orderId = 0)
    {
        $this->method = 'GET';
        $this->path = '/api/v5/trade/orders-history';
        $this->body = array_merge(['instId' => $env == self::ENV_SPOT ? $this->symbol : $this->contractSymbol], array_filter([
            'before' => $orderId,
            'limit'  => $limit
        ]));

        return $this->send();
    }

    public function format($spotSymbol = [], $futureSymbol = false)
    {
        $this->symbol = $spotSymbol['instId'] ?? null;
        $futureSymbol && $this->contractSymbol = $futureSymbol['instId'];
        $this->baseCurrency = $spotSymbol['baseCcy'] ?? null;
        $this->quoteCurrency = $spotSymbol['quoteCcy'] ?? null;
        $this->pricePrecision = intval($futureSymbol ? log($futureSymbol['tickSz'], 0.1) : log($spotSymbol['tickSz'], 0.1));
        $this->quantityPrecision = intval(log($spotSymbol['lotSz'], 0.1));
        $this->contractSize = $futureSymbol ? $futureSymbol['ctVal'] : 0;
        $this->status = $spotSymbol['state'] == 'live';
        $this->extra = ['spot' => $spotSymbol, 'future' => $futureSymbol,];

        return $this->raw();
    }


    public function formatPeriod($period)
    {
        if (Str::contains($period, 'min')) {
            return Str::replace('min', 'm', $period);
        }

        if (Str::contains($period, 'hour')) {
            return Str::replace('hour', 'H', $period);
        }

        if (Str::contains($period, 'day')) {
            return Str::replace('day', 'D', $period);
        }

        if (Str::contains($period, 'week')) {
            return Str::replace('week', 'W', $period);
        }

        if (Str::contains($period, 'mon')) {
            return Str::replace('mon', 'M', $period);
        }

        if (Str::contains($period, 'year')) {
            return Str::replace('year', 'Y', $period);
        }


        return $period;
    }

}
