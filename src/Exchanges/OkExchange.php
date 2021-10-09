<?php

namespace Aoeng\Laravel\Exchange\Exchanges;

use Aoeng\Laravel\Exchange\Contracts\ExchangeInterface;
use Aoeng\Laravel\Exchange\Requests\OkRequestTrait;
use Aoeng\Laravel\Exchange\Symbols\OkSymbol;
use Aoeng\Laravel\Exchange\Traits\HttpRequestTrait;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

class OkExchange implements ExchangeInterface
{
    use OkRequestTrait;

    const POSITION_MODE_DOUBLE = 'long_short_mode';
    const POSITION_MODE_SINGLE = 'net_mode';

    protected $config;

    protected $symbol = null;

    public function __construct($config = null)
    {
        $this->config = $config;
        $this->key = $config['key'] ?? null;
        $this->secret = $config['secret'] ?? null;
        $this->passphrase = $config['passphrase'] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function name()
    {
        return 'ok';
    }


    /**
     * @inheritDoc
     * @throws GuzzleException
     */
    public function symbols()
    {
        $this->path = '/api/v5/public/instruments';
        $this->body = ['instType' => 'SPOT'];

        $spotResult = $this->send(false);

        $spotSymbols = collect($spotResult)->where('quoteCcy', 'USDT')->keyBy('baseCcy')->toArray();

        $this->body = ['instType' => 'SWAP'];

        $futureResult = $this->send(false);

        $futureSymbols = collect($futureResult)->where('settleCcy', 'USDT')->where('state', 'live')->keyBy('ctValCcy')->toArray();
        foreach ($spotSymbols as $baseAsset => $spotSymbol) {
            $symbols[] = (new OkSymbol())->format($spotSymbol, $futureSymbols[$baseAsset] ?? false);
        }

        return $this->response($symbols ?? []);
    }

    public function symbol($symbol)
    {
        $this->symbol = (new OkSymbol())->symbol($symbol)->keySecret($this->key, $this->secret, $this->passphrase);

        return $this->symbol;
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

        return $this->send();
    }


    /**
     * 更改持仓状态
     * @param string $posMode ["long_short_mode": 双向持仓模式；"net_mode": 单向持仓模式]
     * @return array|mixed
     * @throws GuzzleException
     */
    public function changePositionSide(string $posMode = self::POSITION_MODE_DOUBLE)
    {
        $this->method = 'POST';
        $this->path = '/api/v5/account/set-position-mode';
        $this->body = compact('posMode');

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
        $this->path = '/api/v5/account/balance';

        return $this->send();
    }

    /**
     * 账户持仓信息
     * @return array|mixed
     * @throws GuzzleException
     */
    public function positions()
    {
        $this->method = 'GET';
        $this->path = '/api/v5/account/positions';

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
        $this->url = '/api/v5/account/account-position-risk';
        $this->body = ['instType' => 'SWAP'];

        return $this->send();
    }
}
