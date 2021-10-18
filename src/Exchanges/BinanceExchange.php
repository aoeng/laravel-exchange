<?php

namespace Aoeng\Laravel\Exchange\Exchanges;

use Aoeng\Laravel\Exchange\Adapters\Binance;
use Aoeng\Laravel\Exchange\Contracts\ExchangeInterface;
use Aoeng\Laravel\Exchange\Facades\Exchange;
use Aoeng\Laravel\Exchange\Requests\BinanceRequestTrait;
use Aoeng\Laravel\Exchange\Symbols\BinanceSymbol;
use GuzzleHttp\Exception\GuzzleException;

class BinanceExchange implements ExchangeInterface
{
    use BinanceRequestTrait;


    protected $config;

    protected $symbol = null;


    public function __construct($config = null)
    {
        $this->config = $config;
        $this->key = $config['key'] ?? null;
        $this->secret = $config['secret'] ?? null;
        $this->proxy = $config['proxy'] ?? null;
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

        if (isset($spotResult['code']) && $spotResult['code'] != 0) {
            return $this->error($spotResult['message'], $spotResult['code']);
        }

        $spotSymbols = collect($spotResult['symbols'])->where('quoteAsset', 'USDT')->keyBy('baseAsset')->toArray();

        $this->url = $this->futureHost . '/fapi/v1/exchangeInfo';

        $futureResult = $this->send(false);

        if (isset($futureResult['code']) && $futureResult['code'] != 0) {
            return $this->error($futureResult['message'], $futureResult['code']);
        }

        $futureSymbols = collect($futureResult['symbols'])
            ->where('contractType', 'PERPETUAL')
            ->where('quoteAsset', 'USDT')
            ->where('status', 'TRADING')->keyBy('baseAsset')->toArray();

        foreach ($spotSymbols as $baseAsset => $spotSymbol) {
            $symbols[] = Binance::formatSymbol($spotSymbol, $futureSymbols[$baseAsset] ?? false);
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

        if (isset($result['code']) && $result['code'] != 0) {
            return $this->error($result['message'], $result['code']);
        }

        return $this->response($result['dualSidePosition']);
    }


    /**
     * 更改持仓状态
     * @param string $dualSidePosition ["true": 双向持仓模式；"false": 单向持仓模式]
     * @return array|mixed
     * @throws GuzzleException
     */
    public function changePositionSide(string $dualSidePosition = Exchange::POSITION_MODE_DOUBLE)
    {
        $dualSidePosition = Binance::$positionModeMap[$dualSidePosition];

        $this->method = 'POST';
        $this->url = $this->futureHost . '/fapi/v1/positionSide/dual';
        $this->body = compact('dualSidePosition');

        return $this->send();
    }


    /**
     * 账户余额
     * @return array
     * @throws GuzzleException
     */
    public function balance()
    {
        $this->method = 'GET';
        $this->url = $this->futureHost . '/fapi/v2/balance';

        $futureResult = $this->send();

        if ($futureResult['code'] != 0) {
            return $this->error($futureResult['message'], $futureResult['code']);
        }

        $this->method = 'GET';
        $this->url = $this->spotHost . '/api/v3/account';
        $spotResult = $this->send();

        if ($spotResult['code'] != 0) {
            return $this->error($spotResult['message'], $spotResult['code']);
        }

        return $this->response(['spot' => $spotResult['data'], 'future' => $futureResult['data']]);
    }

    /**
     * 账户信息
     * @return array
     * @throws GuzzleException
     */
    public function positions()
    {
        $this->method = 'GET';
        $this->url = $this->futureHost . '/fapi/v2/account';

        $result = $this->send();

        if ($result['code'] != 0) {
            return $this->error($result['message'], $result['code']);
        }

        $this->url = $this->futureHost . '/fapi/v2/positionRisk';
        $riskResult = $this->send();

        if ($riskResult['code'] != 0) {
            return $this->error($riskResult['message'], $riskResult['code']);
        }

        $ps = collect($result['data']['positions'])->keyBy(function ($p) {
            return $p['symbol'] . $p['positionSide'];
        })->toArray();

        $rs = collect($riskResult['data'])->keyBy(function ($r) {
            return $r['symbol'] . $r['positionSide'];
        })->toArray();

        $positions = [];

        foreach ($ps as $key => $position) {
            if (floatval($position['positionAmt']) == 0) {
                continue;
            }

            $positions[] = Binance::formatPosition($position, $rs[$key] ?? []);
        }

        return $this->response($positions);
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

    public function transfer($amount, $type = Exchange::TRANSFER_SPOT2SWAP, $asset = 'USDT')
    {
        $type = Binance::$transferMap[$type];

        $this->method = 'POST';
        $this->url = $this->spotHost . '/sapi/v1/asset/transfer';
        $this->body = compact('type', 'asset', 'amount');

        return $this->send();
    }
}
