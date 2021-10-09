<?php

namespace Aoeng\Laravel\Exchange\Exchanges;

use Aoeng\Laravel\Exchange\Contracts\ExchangeInterface;
use Aoeng\Laravel\Exchange\Requests\BinanceRequestTrait;
use Aoeng\Laravel\Exchange\Symbols\BinanceSymbol;
use Aoeng\Laravel\Exchange\Traits\HttpRequestTrait;
use Aoeng\Laravel\Exchange\Traits\ResponseTrait;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

class BinanceExchange implements ExchangeInterface
{
    use BinanceRequestTrait;


    protected $config;

    protected $symbol = null;

    const SPOT2FUTURE = 'MAIN_UMFUTURE';
    const FUTURE2SPOT = 'UMFUTURE_MAIN';

    public function __construct($config = null)
    {
        $this->config = $config;
        $this->key = $config['key'] ?? null;
        $this->secret = $config['secret'] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function name()
    {
        return 'binance';
    }

    public function symbol($symbol)
    {
        $this->symbol = (new BinanceSymbol())->symbol($symbol)->keySecret($this->key, $this->secret);

        return $this->symbol;
    }

    /**
     * @inheritDoc
     * @throws GuzzleException
     */
    public function symbols()
    {
        $this->url = $this->spotHost . '/api/v3/exchangeInfo';

        $this->checked = false;

        $spotResult = $this->send(false);

        $spotSymbols = collect($spotResult['symbols'])->where('quoteAsset', 'USDT')->keyBy('baseAsset')->toArray();

        $this->url = $this->futureHost . '/fapi/v1/exchangeInfo';

        $futureResult = $this->send(false);

        $futureSymbols = collect($futureResult['symbols'])->keyBy('baseAsset')->toArray();

        foreach ($spotSymbols as $baseAsset => $spotSymbol) {
            $futureSymbol = $futureSymbols[$baseAsset] ?? false;
            $symbols[] = (new BinanceSymbol())->format($spotSymbol, $futureSymbol);
        }

        return $this->response($symbols ?? []);
    }

    /**
     * 查询APK接口权限
     * @return array|mixed
     * @throws GuzzleException
     */
    public function check()
    {
        $this->url = $this->spotHost . '/sapi/v1/account/apiRestrictions';

        return $this->send();
    }


    /**
     * 服务器时间
     * @throws GuzzleException
     */
    public function time()
    {
        $this->url = $this->futureHost . '/fapi/v1/time';

        return $this->send();
    }


    /**
     * 创建socket授权
     * @throws GuzzleException
     */
    public function createListenKey()
    {
        $this->method = 'POST';
        $this->url = $this->futureHost . '/fapi/v1/listenKey';

        return $this->send();
    }

    /**
     * 延期socket授权
     * @throws GuzzleException
     */
    public function putListenKey()
    {
        $this->method = 'PUT';
        $this->url = $this->futureHost . '/fapi/v1/listenKey';

        return $this->send();
    }


    /**
     * 删除socket授权
     * @throws GuzzleException
     */
    public function deleteListenKey()
    {
        $this->method = 'DELETE';
        $this->url = $this->futureHost . '/fapi/v1/listenKey';

        return $this->send();
    }


    /**
     * 查询持仓模式
     * @return array [true: 双向 ,false: 单向]
     * @throws GuzzleException
     */
    public function positionSide()
    {
        $this->method = 'GET';
        $this->url = $this->futureHost . '/fapi/v1/positionSide/dual';

        $result = $this->send(false);

        return $this->response($result['dualSidePosition']);
    }


    /**
     * 更改持仓状态
     * @param string $dualSidePosition ["true": 双向持仓模式；"false": 单向持仓模式]
     * @return array|mixed
     * @throws GuzzleException
     */
    public function changePositionSide(string $dualSidePosition = 'true')
    {
        $this->method = 'POST';
        $this->url = $this->futureHost . '/fapi/v1/positionSide/dual';
        $this->body = compact('dualSidePosition');

        return $this->send();
    }


    /**
     * 账户余额
     * @return array|mixed
     * @throws GuzzleException
     */
    public function balance()
    {
        $this->method = 'GET';
        $this->url = $this->futureHost . '/fapi/v2/balance';

        return $this->send();
    }

    /**
     * 账户信息
     * @return array|mixed
     * @throws GuzzleException
     */
    public function positions()
    {
        $this->method = 'GET';
        $this->url = $this->futureHost . '/fapi/v2/account';

        return $this->send();
    }

    /**
     * 杠杆分层标准
     * @return array|mixed
     * @throws GuzzleException
     */
    public function leverBracket()
    {
        $this->method = 'GET';
        $this->url = $this->futureHost . '/fapi/v1/leverageBracket';

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

        return $this->send();
    }

    public function transfer($amount, $type = self::SPOT2FUTURE, $asset = 'USDT')
    {
        $this->method = 'POST';
        $this->url = $this->spotHost . '/sapi/v1/asset/transfer';
        $this->body = compact('type', 'asset', 'amount');

        return $this->send();
    }

}
