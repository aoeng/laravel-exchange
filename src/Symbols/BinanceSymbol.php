<?php

namespace Aoeng\Laravel\Exchange\Symbols;

use Aoeng\Laravel\Exchange\Contracts\SymbolInterface;
use Aoeng\Laravel\Exchange\Requests\BinanceRequestTrait;
use Aoeng\Laravel\Exchange\Traits\SymbolTrait;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;

class BinanceSymbol implements SymbolInterface
{
    use SymbolTrait, BinanceRequestTrait;


    const SIDE_SELL = 'sell';
    const SIDE_BUY = 'buy';

    const POSITION_SIDE_SPOT = 'SPOT';
    const POSITION_SIDE_BOTH = 'BOTH';
    const POSITION_SIDE_LONG = 'LONG';
    const POSITION_SIDE_SHORT = 'SHORT';

    const ORDER_TYPE_LIMIT = 'LIMIT';
    const ORDER_TYPE_MARKET = 'MARKET';
    const ORDER_TYPE_STOP = 'STOP';
    const ORDER_TYPE_TAKE_PROFIT = 'TAKE_PROFIT';
    const ORDER_TYPE_STOP_MARKET = 'STOP_MARKET';
    const ORDER_TYPE_TAKE_PROFIT_MARKET = 'TAKE_PROFIT_MARKET';
    const ORDER_TYPE_TRAILING_STOP_MARKET = 'TRAILING_STOP_MARKET';

    const MARGIN_TYPE_ISOLATED = 'ISOLATED';
    const MARGIN_TYPE_CROSSED = 'CROSSED';

    public function symbol($symbol = [])
    {
        foreach ($symbol as $key => $value) {
            $this->$key = $value;
        }


        if (!isset($symbol['symbolFuture'])) {
            $this->symbolFuture = $symbol['symbol'];
        }

        return $this;
    }

    /**
     * @throws GuzzleException
     */
    public function klins($env, $period, $limit = 500)
    {
        $this->url = $env == self::ENV_SPOT ? "$this->spotHost/api/v3/klines" : "$this->futureHost/fapi/v1/klines";
        $this->checked = false;

        $this->body = [
            'symbol'   => $this->symbol,
            'interval' => $this->formatPeriod($period),
            'limit'    => $limit
        ];

        return $this->send();
    }


    public function leverBracket()
    {
        $this->method = 'GET';
        $this->url = $this->futureHost . '/fapi/v1/leverageBracket';
        $this->body = ['symbol' => $this->symbolFuture];

        return $this->send();
    }

    public function changeLever($leverage)
    {
        $this->method = 'POST';
        $this->url = $this->futureHost . '/fapi/v1/leverage';
        $this->body = ['symbol' => $this->symbolFuture, 'leverage' => $leverage];

        return $this->send();
    }

    /**
     *  更改全逐仓
     * @param string $marginType [ISOLATED , CROSSED]
     * @return array|mixed
     * @throws GuzzleException
     */
    public function changeMarginModel(string $marginType = self::MARGIN_TYPE_CROSSED)
    {
        $this->method = 'POST';
        $this->url = $this->futureHost . '/fapi/v1/marginType';
        $this->body = ['symbol' => $this->symbolFuture, 'marginType' => $marginType];

        return $this->send();
    }

    /**
     * 调整逐仓保证金
     * @param $amount
     * @param int $type [调整方向 1: 增加逐仓保证金，2: 减少逐仓保证金]
     * @param string $positionSide ['BOTH'] 双向持仓必填['LONG','SHORT']
     * @return array|mixed
     * @throws GuzzleException
     */
    public function changeMargin($amount, int $type = 1, string $positionSide = self::POSITION_SIDE_BOTH)
    {
        $this->method = 'POST';
        $this->url = $this->futureHost . '/fapi/v1/positionMargin';
        $this->body = [
            'symbol'       => $this->symbolFuture,
            'amount'       => $amount,
            'type'         => $type,
            'positionSide' => $positionSide
        ];

        return $this->send();
    }

    /**
     * 用户持仓风险
     * @return array|mixed
     * @throws GuzzleException
     */
    public function positionRisk()
    {
        $this->method = 'GET';
        $this->url = $this->futureHost . '/fapi/v2/positionRisk';
        $this->body = ['symbol' => $this->symbolFuture];

        return $this->send();
    }


    public function createFutureOrder($quantity, $positionSide = self::POSITION_SIDE_BOTH, $side = self::SIDE_BUY, $type = self::ORDER_TYPE_MARKET, $price = null, $newClientOrderId = null)
    {
        $this->method = 'POST';
        $this->url = $this->futureHost . '/fapi/v1/order';
        $this->body = array_merge(['symbol' => $this->symbolFuture], array_filter(compact('side', 'positionSide', 'type', 'quantity', 'price', 'newClientOrderId')));

        return $this->send();
    }

    public function createOrder($quantity, $side = self::SIDE_BUY, $type = self::ORDER_TYPE_MARKET, $price = null, $quoteOrderQty = null, $newClientOrderId = null)
    {
        $this->method = 'POST';
        $this->url = $this->spotHost . '/api/v3/order';
        $this->body = array_merge(['symbol' => $this->symbolFuture], array_filter(compact('side', 'type', 'quantity', 'price', 'quoteOrderQty', 'newClientOrderId')));

        return $this->send();
    }

    public function createFutureTrailingOrder($quantity, $activationPrice = null, $callbackRate = 0.1, $positionSide = self::POSITION_SIDE_BOTH, $side = self::SIDE_BUY, $newClientOrderId = null)
    {
        $this->method = 'POST';
        $this->url = $this->futureHost . '/fapi/v1/order';
        $this->body = array_merge([
            'symbol' => $this->symbolFuture,
            'type'   => self::ORDER_TYPE_TRAILING_STOP_MARKET
        ], array_filter(compact('side', 'positionSide', 'activationPrice', 'quantity', 'callbackRate', 'newClientOrderId')));

        return $this->send();
    }


    public function open($positionSide, $volume, $price = 0, $rate = 0)
    {
        if ($positionSide == self::POSITION_SIDE_SPOT) {
            if ($rate != 0) {
                return $this->error('币安现货不支持追踪委托订单');
            }
            return $this->createOrder($volume, self::SIDE_BUY, $price == 0 ? self::ORDER_TYPE_MARKET : self::ORDER_TYPE_LIMIT, $price);
        }

        if ($rate == 0) {
            return $this->createFutureOrder($volume, $positionSide, $positionSide == self::POSITION_SIDE_SHORT ? self::SIDE_SELL : self::SIDE_BUY, $price == 0 ? self::ORDER_TYPE_MARKET : self::ORDER_TYPE_LIMIT, $price);
        }

        return $this->createFutureTrailingOrder($volume, $price, $rate, $positionSide, $positionSide == self::POSITION_SIDE_SHORT ? self::SIDE_SELL : self::SIDE_BUY);
    }

    public function close($positionSide, $volume, $price = 0, $rate = 0)
    {

        if ($positionSide == self::POSITION_SIDE_SPOT) {
            if ($rate != 0) {
                return $this->error('币安现货不支持追踪委托订单');
            }
            return $this->createOrder($volume, self::SIDE_SELL, $price == 0 ? self::ORDER_TYPE_MARKET : self::ORDER_TYPE_LIMIT, $price);
        }

        if ($rate == 0) {
            return $this->createFutureOrder($volume, $positionSide, $positionSide == self::POSITION_SIDE_SHORT ? self::SIDE_BUY : self::SIDE_SELL, $price == 0 ? self::ORDER_TYPE_MARKET : self::ORDER_TYPE_LIMIT, $price);
        }

        return $this->createFutureTrailingOrder($volume, $price, $rate, $positionSide, $positionSide == self::POSITION_SIDE_SHORT ? self::SIDE_BUY : self::SIDE_SELL);
    }

    public function cancelOrder($env, $orderId, $origClientOrderId = 0)
    {
        $this->method = 'DELETE';
        $this->url = $env == self::ENV_SPOT ? "$this->spotHost/api/v3/order" : "$this->futureHost/fapi/v1/order";
        $this->body = array_merge(['symbol' => $this->symbol], array_filter(compact('orderId', 'origClientOrderId')));

        return $this->send();
    }


    public function cancelAllOrder($env = self::ENV_SPOT)
    {
        $this->method = 'DELETE';
        $this->url = $env == self::ENV_SPOT ? "$this->spotHost/api/v3/openOrders" : "$this->futureHost/fapi/v1/allOpenOrders";
        $this->body = ['symbol' => $this->symbol];

        return $this->send();
    }


    public function searchOrder($env, $orderId, $origClientOrderId = 0)
    {
        $this->method = 'GET';
        $this->url = $env == self::ENV_SPOT ? "$this->spotHost/api/v3/order" : "$this->futureHost/fapi/v1/order";
        $this->body = array_merge(['symbol' => $this->symbolFuture], array_filter(compact('orderId', 'origClientOrderId')));

        return $this->send();
    }


    public function transactionRecord($env, $limit = 500, $fromId = 0, $startTime = null, $endTime = null)
    {
        $this->method = 'GET';
        $this->url = $env == self::ENV_SPOT ? "$this->spotHost/api/v3/myTrades" : "$this->futureHost/fapi/v1/userTrades";
        $this->body = array_merge(['symbol' => $this->symbolFuture], array_filter(compact('fromId', 'startTime', 'endTime', 'limit')));

        return $this->send();
    }


    public function orderRecord($env, $limit = 500, $orderId = 0, $startTime = null, $endTime = null)
    {
        $this->method = 'GET';
        $this->url = $env == self::ENV_SPOT ? "$this->spotHost/api/v3/allOrders" : "$this->futureHost/fapi/v1/allOrders";
        $this->body = array_merge(['symbol' => $this->symbolFuture], array_filter(compact('orderId', 'startTime', 'endTime', 'limit')));

        return $this->send();
    }

    public function format($spotSymbol = [], $futureSymbol = false)
    {
        $this->symbol = $spotSymbol['symbol'] ?? null;
        $futureSymbol && $this->symbolFuture = $futureSymbol['symbol'];
        $this->baseCurrency = $spotSymbol['baseAsset'] ?? null;
        $this->quoteCurrency = $spotSymbol['quoteAsset'] ?? null;
        $this->pricePrecision = $futureSymbol['pricePrecision'] ?? $spotSymbol['quotePrecision'];
        $this->quantityPrecision = $futureSymbol['quantityPrecision'] ?? $spotSymbol['baseAssetPrecision'];
        $this->status = $spotSymbol['status'] == 'TRADING';
        $this->extra = ['spot' => $spotSymbol, 'future' => $futureSymbol,];

        return $this->raw();
    }


    public function formatPeriod($period)
    {
        if (Str::contains($period, 'min')) {
            return Str::replace('min', 'm', $period);
        }

        if (Str::contains($period, 'hour')) {
            return Str::replace('hour', 'h', $period);
        }

        if (Str::contains($period, 'day')) {
            return Str::replace('day', 'd', $period);
        }

        if (Str::contains($period, 'week')) {
            return Str::replace('week', 'w', $period);
        }

        if (Str::contains($period, 'mon')) {
            return Str::replace('mon', 'm', $period);
        }

        if (Str::contains($period, 'year')) {
            return Str::replace('year', 'y', $period);
        }


        return $period;
    }

}
