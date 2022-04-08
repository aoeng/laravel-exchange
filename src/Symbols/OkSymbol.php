<?php

namespace Aoeng\Laravel\Exchange\Symbols;

use Aoeng\Laravel\Exchange\Adapters\Ok;
use Aoeng\Laravel\Exchange\Contracts\SymbolInterface;
use Aoeng\Laravel\Exchange\Exceptions\ExchangeException;
use Aoeng\Laravel\Exchange\Facades\Exchange;
use Aoeng\Laravel\Exchange\Requests\OkRequestTrait;
use Aoeng\Laravel\Exchange\Traits\SymbolTrait;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;

class OkSymbol implements SymbolInterface
{
    use SymbolTrait, OkRequestTrait;


    const ORDER_CREATE_TYPE_N = 'n';
    const ORDER_CREATE_TYPE_S = 's';

    public function __construct($config = [])
    {
        $this->config = $config;

        $this->key = $config['key'] ?? null;
        $this->secret = $config['secret'] ?? null;
        $this->passphrase = $config['passphrase'] ?? null;
        $this->simulated = $config['simulated'] ?? false;
        $this->proxy = $config['proxy'] ?? null;
    }


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
            'instId' => $env == Exchange::ENV_SPOT ? $this->symbol : $this->contractSymbol,
            'bar'    => Ok::formatPeriod($period),
            'limit'  => $limit
        ], array_filter(compact('before', 'after')));

        return $this->send();
    }


    public function changeLever($leverage, $positionType = Exchange::POSITION_TYPE_CROSSED, $positionSide = Exchange::POSITION_SIDE_BOTH)
    {
        $this->method = 'POST';
        $this->path = '/api/v5/account/set-leverage';
        $this->body = [
            'instId'  => $this->contractSymbol,
            'lever'   => $leverage,
            'mgnMode' => Ok::$positionTypeMap[$positionType],
            'posSide' => Ok::$positionSideMap[$positionSide]
        ];

        $result = $this->send();

        if ($result['code'] != 0) {
            return $this->error($result['message'], $result['code']);
        }

        return $this->response($result['data'][0]);
    }

    public function maxExchange($positionType = Exchange::POSITION_TYPE_CROSSED)
    {
        $this->method = 'GET';
        $this->path = '/api/v5/account/max-size';
        $this->body = [
            'instId' => $this->contractSymbol,
            'tdMode' => Ok::$positionTypeMap[$positionType]
        ];

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
    public function changeMargin($amount, string $type = Exchange::MARGIN_CHANGE_ADD, string $positionSide = Exchange::POSITION_SIDE_BOTH)
    {
        $this->method = 'POST';
        $this->path = '/api/v5/account/position/margin-balance';
        $this->body = [
            'instId'  => $this->contractSymbol,
            'amt'     => $amount,
            'type'    => Ok::$marginChangeMap[$type],
            'posSide' => Ok::$positionSideMap[$positionSide]
        ];

        return $this->send();
    }

    public function createOrder($quantity, $orderSide = Exchange::ORDER_SIDE_BUY, $orderType = Exchange::ORDER_TYPE_MARKET, $price = null, $quantityType = Exchange::ORDER_QUANTITY_TYPE_BASE, $newClientOrderId = null)
    {
        $this->method = 'POST';
        $this->path = '/api/v5/trade/order';
        $this->body = array_merge(['instId' => $this->symbol], array_filter([
            'tdMode'  => Ok::$positionTypeMap[Exchange::POSITION_TYPE_CASH],
            'clOrdId' => $newClientOrderId,
            'side'    => Ok::$orderSideMap[$orderSide],
            'ordType' => Ok::$orderTypeMap[$orderType],
            'sz'      => $quantity,
            'px'      => $price,
            'tgtCcy'  => Ok::$orderQuantityTypeMap[$quantityType],
        ]));

        $result = $this->send();

        if ($result['code'] != 0) {
            return $this->error($result['message'], $result['code']);
        }

        return $this->response(Ok::formatOrder($result['data'][0]));
    }

    public function createFutureOrder($quantity, $positionSide = Exchange::POSITION_SIDE_BOTH, $orderSide = Exchange::ORDER_SIDE_BUY, $positionType = Exchange::POSITION_TYPE_CASH, $orderType = Exchange::ORDER_TYPE_MARKET, $price = null, $newClientOrderId = null)
    {
        $this->method = 'POST';
        $this->path = '/api/v5/trade/order';
        $this->body = array_merge(['instId' => $this->contractSymbol], array_filter([
            'tdMode'  => Ok::$positionTypeMap[$positionType],
            'clOrdId' => $newClientOrderId,
            'side'    => Ok::$orderSideMap[$orderSide],
            'posSide' => Ok::$positionSideMap[$positionSide],
            'ordType' => Ok::$orderTypeMap[$orderType],
            'sz'      => $quantity,
            'px'      => $price,
        ]));

        $result = $this->send();

        if ($result['code'] != 0) {
            return $this->error($result['message'], $result['code']);
        }

        return $this->response(Ok::formatOrder($result['data'][0]));
    }

    public function createFutureTrailingOrder($quantity, $callbackRate = 0.1, $activationPrice = null, $positionSide = Exchange::POSITION_SIDE_BOTH, $orderSide = Exchange::ORDER_SIDE_BUY, $positionType = Exchange::POSITION_TYPE_CASH)
    {
        $this->method = 'POST';
        $this->path = '/api/v5/trade/order-algo';
        $this->body = array_merge(['instId' => $this->contractSymbol], array_filter([
            'tdMode'        => Ok::$positionTypeMap[$positionType],
            'side'          => Ok::$orderSideMap[$orderSide],
            'posSide'       => Ok::$positionSideMap[$positionSide],
            'ordType'       => Ok::$orderTypeMap[Exchange::ORDER_TYPE_TRAILING],
            'sz'            => $quantity,
            'activePx'      => $activationPrice,
            'callbackRatio' => $callbackRate / 100,
        ]));

        $result = $this->send();

        if ($result['code'] != 0) {
            return $this->error($result['message'], $result['code']);
        }

        return $this->response(Ok::formatOrder($result['data'][0]));
    }


    public function open($positionSide, $volume, $env = Exchange::ENV_SWAP, $price = 0, $rate = 0)
    {
        $orderType = $price == 0 ? Exchange::ORDER_TYPE_MARKET : Exchange::ORDER_TYPE_LIMIT;

        if ($env == Exchange::ENV_SPOT) {
            return $this->createOrder($volume, Exchange::ORDER_SIDE_BUY, $orderType, $price);
        }

        $orderSide = $positionSide == Exchange::POSITION_SIDE_SHORT ? Exchange::ORDER_SIDE_SELL : Exchange::ORDER_SIDE_BUY;

        if ($rate == 0) {
            return $this->createFutureOrder($volume, $positionSide, $orderSide, Exchange::POSITION_TYPE_CROSSED, $orderType, $price);
        }

        return $this->createFutureTrailingOrder($volume, $rate, $price, $positionSide, $orderSide, Exchange::POSITION_TYPE_CROSSED);
    }

    public function close($positionSide, $volume, $env = Exchange::ENV_SWAP, $price = 0, $rate = 0)
    {
        $orderType = $price == 0 ? Exchange::ORDER_TYPE_MARKET : Exchange::ORDER_TYPE_LIMIT;

        if ($env == Exchange::ENV_SPOT) {
            return $this->createOrder($volume, Exchange::ORDER_SIDE_SELL, $orderType, $price);
        }
        $orderSide = $positionSide == Exchange::POSITION_SIDE_SHORT ? Exchange::ORDER_SIDE_BUY : Exchange::ORDER_SIDE_SELL;

        if ($rate == 0) {
            return $this->createFutureOrder($volume, $positionSide, $orderSide, Exchange::POSITION_TYPE_CROSSED, $orderType, $price);
        }

        return $this->createFutureTrailingOrder($volume, $rate, $price, $positionSide, $orderSide, Exchange::POSITION_TYPE_CROSSED);
    }

    public function cancelOrder($orderId, $env = Exchange::ENV_SWAP, $origClientOrderId = 0)
    {
        $this->method = 'POST';
        $this->path = '/api/v5/trade/cancel-order';
        $this->body = array_merge(['instId' => $env == Exchange::ENV_SPOT ? $this->symbol : $this->contractSymbol],
            array_filter([
                'ordId'   => $orderId,
                'clOrdId' => $origClientOrderId
            ]));

        return $this->send();
    }


    public function searchOrder($orderId, $env = Exchange::ENV_SWAP, $origClientOrderId = 0)
    {
        $this->method = 'GET';
        $this->path = '/api/v5/trade/order';
        $this->body = array_merge(['instId' => $env == Exchange::ENV_SPOT ? $this->symbol : $this->contractSymbol],
            array_filter([
                'ordId'   => $orderId,
                'clOrdId' => $origClientOrderId
            ]));

        return $this->send();
    }


    public function transactions($limit = 500, $env = Exchange::ENV_SWAP, $fromId = 0)
    {
        $this->method = 'GET';
        $this->path = '/api/v5/trade/fills';
        $this->body = array_merge(['instId' => $env == Exchange::ENV_SPOT ? $this->symbol : $this->contractSymbol],
            array_filter([
                'instType' => $env,
                'before'   => $fromId,
                'limit'    => $limit
            ]));

        return $this->send();
    }


    public function pendingOrders($limit = 500, $env = Exchange::ENV_SWAP, $fromId = 0)
    {
        $this->method = 'GET';
        $this->path = '/api/v5/trade/orders-pending';
        $this->body = array_merge(['instId' => $env == Exchange::ENV_SPOT ? $this->symbol : $this->contractSymbol],
            array_filter([
                'before' => $fromId,
                'limit'  => $limit
            ]));

        return $this->send();
    }

    public function historyOrders($limit = 500, $env = Exchange::ENV_SWAP, $fromId = 0)
    {
        $this->method = 'GET';
        $this->path = '/api/v5/trade/orders-history';
        $this->body = array_merge(['instId' => $env == Exchange::ENV_SPOT ? $this->symbol : $this->contractSymbol],
            array_filter([
                'before' => $fromId,
                'limit'  => $limit
            ]));

        return $this->send();
    }

}
