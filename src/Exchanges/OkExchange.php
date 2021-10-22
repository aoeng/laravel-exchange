<?php

namespace Aoeng\Laravel\Exchange\Exchanges;

use Aoeng\Laravel\Exchange\Adapters\Ok;
use Aoeng\Laravel\Exchange\Contracts\ExchangeInterface;
use Aoeng\Laravel\Exchange\Facades\Exchange;
use Aoeng\Laravel\Exchange\Requests\OkRequestTrait;
use Aoeng\Laravel\Exchange\Symbols\OkSymbol;
use GuzzleHttp\Exception\GuzzleException;

class OkExchange implements ExchangeInterface
{
    use OkRequestTrait;

    protected $symbol = null;

    public function __construct($config = null)
    {
        $this->config = $config;
        $this->key = $config['key'] ?? null;
        $this->secret = $config['secret'] ?? null;
        $this->passphrase = $config['passphrase'] ?? null;
        $this->simulated = $config['simulated'] ?? false;
        $this->proxy = $config['proxy'] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function name()
    {
        return 'ok';
    }


    public function symbol($symbol)
    {
        $this->symbol = (new OkSymbol($this->config))->symbol($symbol);

        return $this->symbol;
    }

    /**
     * @inheritDoc
     * @throws GuzzleException
     */
    public function symbols()
    {
        $this->path = '/api/v5/public/instruments';
        $this->body = ['instType' => Exchange::ENV_SPOT];

        $spotResult = $this->send(false);

        if (isset($spotResult['code']) && $spotResult['code'] != 0) {
            return $this->error($spotResult['message'], $spotResult['code']);
        }

        $spotSymbols = collect($spotResult)->where('quoteCcy', 'USDT')->keyBy('baseCcy')->toArray();

        $this->body = ['instType' => Exchange::ENV_SWAP];

        $futureResult = $this->send(false);

        if (isset($futureResult['code']) && $futureResult['code'] != 0) {
            return $this->error($futureResult['message'], $futureResult['code']);
        }

        $futureSymbols = collect($futureResult)->where('settleCcy', 'USDT')->where('state', 'live')->keyBy('ctValCcy')->toArray();
        foreach ($spotSymbols as $baseAsset => $spotSymbol) {
            $symbols[] = Ok::formatSymbol($spotSymbol, $futureSymbols[$baseAsset] ?? false);
        }

        return $this->response($symbols ?? []);
    }


    /**
     * 服务器时间
     * @throws GuzzleException
     */
    public function time()
    {
        $this->method = 'GET';
        $this->path = '/api/v5/public/time';

        return $this->send();
    }

    /**
     * 查看当前账户的配置信息
     * @return array [true: 双向 ,false: 单向]
     * @throws GuzzleException
     */
    public function positionSide()
    {
        $this->method = 'GET';

        $this->path = '/api/v5/account/config';

        $result = $this->send();

        if ($result['code'] != 0) {
            return $this->error($result['message'], $result['code']);
        }

        return $this->response($result['data'][0]);
    }


    /**
     * 更改持仓状态
     * @param string $positionMode ["long_short_mode": 双向持仓模式；"net_mode": 单向持仓模式]
     * @return array|mixed
     * @throws GuzzleException
     */
    public function changePositionSide(string $positionMode = Exchange::POSITION_MODE_DOUBLE)
    {
        $this->method = 'POST';
        $this->path = '/api/v5/account/set-position-mode';
        $this->body = ['posMode' => Ok::$positionModeMap[$positionMode]];

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
        $this->path = '/api/v5/account/balance';

        $result = $this->send();

        if ($result['code'] != 0) {
            return $this->error($result['message'], $result['code']);
        }

        return $this->response(Ok::formatBalance($result['data']));
    }

    /**
     * 账户持仓信息
     * @return array
     * @throws GuzzleException
     */
    public function positions()
    {
        $this->method = 'GET';
        $this->path = '/api/v5/account/positions';

        $result = $this->send();

        if ($result['code'] != 0) {
            return $this->error($result['message'], $result['code']);
        }

        $crossPs = collect($result['data'])->where('instType', Exchange::ENV_SWAP)
            ->where('mgnMode', Ok::$positionTypeMap[Exchange::POSITION_TYPE_CROSSED])
            ->keyBy('instId')->keys()->chunk(5)->toArray();

        $isolatedPs = collect($result['data'])->where('instType', Exchange::ENV_SWAP)
            ->where('mgnMode', Ok::$positionTypeMap[Exchange::POSITION_TYPE_ISOLATED])
            ->keyBy('instId')->keys()->chunk(5)->toArray();

        $ms = [];
        if (!empty($crossPs)) {
            foreach ($crossPs as $p) {
                $res = $this->maxSide($p);
                if ($res['code'] != 0) {
                    continue;
                }
                $ms[Exchange::POSITION_TYPE_CROSSED] = array_merge($ms, $res['data']);
            }
            $ms[Exchange::POSITION_TYPE_CROSSED] = collect($ms[Exchange::POSITION_TYPE_CROSSED])
                ->keyBy('instId')->toArray();
        }

        if (!empty($isolatedPs)) {
            foreach ($isolatedPs as $p) {
                $res = $this->maxSide($p);
                if ($res['code'] != 0) {
                    continue;
                }
                $ms[Exchange::POSITION_TYPE_ISOLATED] = array_merge($ms, $res['data']);
            }
            $ms[Exchange::POSITION_TYPE_ISOLATED] = collect($ms[Exchange::POSITION_TYPE_ISOLATED])
                ->keyBy('instId')->toArray();
        }

        $positions = [];

        foreach ($result['data'] as $position) {
            if (!isset($position['instType']) || $position['instType'] != Exchange::ENV_SWAP) {
                continue;
            }

            $maxSize = [];
            if ($position['mgnMode'] == Ok::$positionTypeMap[Exchange::POSITION_TYPE_CROSSED]) {
                $maxSize = $ms[Exchange::POSITION_TYPE_CROSSED][$position['instId']] ?? [];
            }

            if ($position['mgnMode'] == Ok::$positionTypeMap[Exchange::POSITION_TYPE_ISOLATED]) {
                $maxSize = $ms[Exchange::POSITION_TYPE_ISOLATED][$position['instId']] ?? [];
            }

            $positions[] = Ok::formatPosition($position, $maxSize);
        }

        return $this->response($positions);
    }


    public function maxSide(array $symbols = [], $positionType = Exchange::POSITION_TYPE_CROSSED)
    {
        $this->method = 'GET';
        $this->path = '/api/v5/account/max-size';
        $this->body = [
            'instId' => implode(',', $symbols),
            'tdMode' => Ok::$positionTypeMap[$positionType]
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
        $this->path = '/api/v5/account/account-position-risk';
        $this->body = ['instType' => Exchange::ENV_SWAP];

        return $this->send();
    }

}
