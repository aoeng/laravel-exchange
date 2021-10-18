<?php

namespace Aoeng\Laravel\Exchange\Symbols;

use Aoeng\Laravel\Exchange\Adapters\Binance;
use Aoeng\Laravel\Exchange\Contracts\SymbolInterface;
use Aoeng\Laravel\Exchange\Exceptions\ExchangeException;
use Aoeng\Laravel\Exchange\Facades\Exchange;
use Aoeng\Laravel\Exchange\Requests\BinanceRequestTrait;
use Aoeng\Laravel\Exchange\Traits\SymbolTrait;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;

class BinanceSymbol implements SymbolInterface
{
    use SymbolTrait, BinanceRequestTrait;


    public function symbol($symbol = [])
    {

        if (!isset($symbol['symbol'])) {
            throw new ExchangeException('交易对[symbol]不存在');
        }

        foreach ($symbol as $key => $value) {
            $key = Str::camel($key);
            $this->$key = $value;
        }
        if (Str::contains($this->symbol, '-')) {
            $this->symbol = Str::replace('-', '', $this->symbol);
        }

        $this->contractSymbol = $this->symbol;

        return $this;
    }

    /**
     * @throws GuzzleException
     */
    public function klins($env, $period, $limit = 500)
    {
        $this->url = $env == Exchange::ENV_SPOT ? "$this->spotHost/api/v3/klines" : "$this->futureHost/fapi/v1/klines";
        $this->checked = false;

        $this->body = [
            'symbol'   => $this->symbol,
            'interval' => Binance::formatPeriod($period),
            'limit'    => $limit
        ];

        return $this->send();
    }


    public function leverBracket()
    {
        $this->method = 'GET';
        $this->url = $this->futureHost . '/fapi/v1/leverageBracket';
        $this->body = ['symbol' => $this->contractSymbol];

        return $this->send();
    }

    public function changeLever($leverage)
    {
        $this->method = 'POST';
        $this->url = $this->futureHost . '/fapi/v1/leverage';
        $this->body = ['symbol' => $this->contractSymbol, 'leverage' => $leverage];

        return $this->send();
    }

    /**
     *  更改全逐仓
     * @param string $marginType [ISOLATED , CROSSED]
     * @return array|mixed
     * @throws GuzzleException
     */
    public function changeMarginModel(string $marginType = Exchange::POSITION_TYPE_CROSSED)
    {

        $this->method = 'POST';
        $this->url = $this->futureHost . '/fapi/v1/marginType';
        $this->body = ['symbol' => $this->contractSymbol, 'marginType' => Binance::$positionTypeMap[$marginType]];

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
    public function changeMargin($amount, int $type = Exchange::MARGIN_CHANGE_ADD, string $positionSide = Exchange::POSITION_SIDE_BOTH)
    {
        $this->method = 'POST';
        $this->url = $this->futureHost . '/fapi/v1/positionMargin';
        $this->body = [
            'symbol'       => $this->contractSymbol,
            'amount'       => $amount,
            'type'         => Binance::$marginChangeMap[$type],
            'positionSide' => Binance::$positionSideMap[$positionSide]
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
        $this->body = ['symbol' => $this->contractSymbol];

        return $this->send();
    }


    public function createFutureOrder($quantity, $positionSide = Exchange::POSITION_SIDE_BOTH, $side = Exchange::ORDER_SIDE_BUY, $type = Exchange::ORDER_TYPE_MARKET, $price = null, $newClientOrderId = null)
    {
        $positionSide = Binance::$positionSideMap[$positionSide];
        $side = Binance::$orderSideMap[$side];
        $type = Binance::$orderTypeMap[$type];

        $this->method = 'POST';
        $this->url = $this->futureHost . '/fapi/v1/order';
        $this->body = array_merge(['symbol' => $this->contractSymbol], array_filter(compact('side', 'positionSide', 'type', 'quantity', 'price', 'newClientOrderId')));

        $result = $this->send();

        if ($result['code'] != 0) {
            return $this->error($result['message'], $result['code']);
        }

        return $this->response(Binance::formatOrder($result['data']));
    }

    public function createOrder($quantity, $side = Exchange::ORDER_SIDE_BUY, $type = Exchange::ORDER_TYPE_MARKET, $price = null, $quoteOrderQty = null, $newClientOrderId = null)
    {

        $side = Binance::$orderSideMap[$side];
        $type = Binance::$orderTypeMap[$type];

        $this->method = 'POST';
        $this->url = $this->spotHost . '/api/v3/order';
        $this->body = array_merge(['symbol' => $this->contractSymbol], array_filter(compact('side', 'type', 'quantity', 'price', 'quoteOrderQty', 'newClientOrderId')));

        $result = $this->send();

        if ($result['code'] != 0) {
            return $this->error($result['message'], $result['code']);
        }

        return $this->response(Binance::formatOrder($result['data']));
    }

    public function createFutureTrailingOrder($quantity, $activationPrice = null, $callbackRate = 0.1, $positionSide = Exchange::POSITION_SIDE_BOTH, $side = Exchange::ORDER_SIDE_BUY, $newClientOrderId = null)
    {
        $positionSide = Binance::$positionSideMap[$positionSide];
        $side = Binance::$orderSideMap[$side];

        $this->method = 'POST';
        $this->url = $this->futureHost . '/fapi/v1/order';
        $this->body = array_merge([
            'symbol' => $this->contractSymbol,
            'type'   => Binance::$orderTypeMap[Exchange::ORDER_TYPE_TRAILING]
        ], array_filter(compact('side', 'positionSide', 'activationPrice', 'quantity', 'callbackRate', 'newClientOrderId')));

        $result = $this->send();

        if ($result['code'] != 0) {
            return $this->error($result['message'], $result['code']);
        }

        return $this->response(Binance::formatOrder($result['data']));
    }


    public function open($positionSide, $volume, $env = Exchange::ENV_SWAP, $price = 0, $rate = 0)
    {
        $orderType = $price == 0 ? Binance::$orderTypeMap[Exchange::ORDER_TYPE_MARKET] : Binance::$orderTypeMap[Exchange::ORDER_TYPE_LIMIT];

        if ($env == Exchange::ENV_SPOT) {
            return $this->createOrder($volume, Binance::$orderSideMap[Exchange::ORDER_SIDE_BUY], $orderType, $price);
        }

        $orderSide = $positionSide == Exchange::POSITION_SIDE_SHORT ? Binance::$orderSideMap[Exchange::ORDER_SIDE_SELL] : Binance::$orderSideMap[Exchange::ORDER_SIDE_BUY];

        if ($rate == 0) {
            return $this->createFutureOrder($volume, $positionSide, $orderSide, $orderType, $price);
        }

        return $this->createFutureTrailingOrder($volume, $price, $rate, $positionSide, $orderSide);
    }

    public function close($positionSide, $volume, $env = Exchange::ENV_SWAP, $price = 0, $rate = 0)
    {
        $orderType = $price == 0 ? Binance::$orderTypeMap[Exchange::ORDER_TYPE_MARKET] : Binance::$orderTypeMap[Exchange::ORDER_TYPE_LIMIT];

        if ($env == Exchange::ENV_SPOT) {
            return $this->createOrder($volume, Binance::$orderSideMap[Exchange::ORDER_SIDE_BUY], $orderType, $price);
        }

        $orderSide = $positionSide == Exchange::POSITION_SIDE_SHORT ? Binance::$orderSideMap[Exchange::ORDER_SIDE_BUY] : Binance::$orderSideMap[Exchange::ORDER_SIDE_SELL];

        if ($rate == 0) {
            return $this->createFutureOrder($volume, $positionSide, $orderSide, $orderType, $price);
        }

        return $this->createFutureTrailingOrder($volume, $price, $rate, $positionSide, $orderSide);
    }

    public function cancelOrder($orderId, $env = Exchange::ENV_SWAP, $origClientOrderId = 0)
    {
        $this->method = 'DELETE';
        $this->url = $env == Exchange::ENV_SPOT ? "$this->spotHost/api/v3/order" : "$this->futureHost/fapi/v1/order";
        $this->body = array_merge(['symbol' => $this->symbol], array_filter(compact('orderId', 'origClientOrderId')));

        return $this->send();
    }


    public function cancelAllOrder($env = Exchange::ENV_SWAP)
    {
        $this->method = 'DELETE';
        $this->url = $env == Exchange::ENV_SPOT ? "$this->spotHost/api/v3/openOrders" : "$this->futureHost/fapi/v1/allOpenOrders";
        $this->body = ['symbol' => $this->symbol];

        return $this->send();
    }


    public function searchOrder($orderId, $env = Exchange::ENV_SWAP, $origClientOrderId = 0)
    {
        $this->method = 'GET';
        $this->url = $env == Exchange::ENV_SPOT ? "$this->spotHost/api/v3/order" : "$this->futureHost/fapi/v1/order";
        $this->body = array_merge(['symbol' => $this->contractSymbol], array_filter(compact('orderId', 'origClientOrderId')));

        return $this->send();
    }


    public function transactions($limit = 500, $env = Exchange::ENV_SWAP, $fromId = 0, $startTime = null, $endTime = null)
    {
        $this->method = 'GET';
        $this->url = $env == Exchange::ENV_SPOT ? "$this->spotHost/api/v3/myTrades" : "$this->futureHost/fapi/v1/userTrades";
        $this->body = array_merge(['symbol' => $this->contractSymbol], array_filter(compact('fromId', 'startTime', 'endTime', 'limit')));

        return $this->send();
    }


    public function orders($limit = 500, $env = Exchange::ENV_SWAP, $fromId = 0, $startTime = null, $endTime = null)
    {
        $this->method = 'GET';
        $this->url = $env == Exchange::ENV_SPOT ? "$this->spotHost/api/v3/allOrders" : "$this->futureHost/fapi/v1/allOrders";
        $this->body = array_merge(['symbol' => $this->contractSymbol], array_filter([
            'orderId'   => $fromId,
            'startTime' => $startTime,
            'endTime'   => $endTime,
            'limit'     => $limit
        ]));

        return $this->send();
    }

}
