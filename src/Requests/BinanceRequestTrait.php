<?php

namespace Aoeng\Laravel\Exchange\Requests;

use Aoeng\Laravel\Exchange\Traits\HttpRequestTrait;
use Aoeng\Laravel\Exchange\Traits\ResponseTrait;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

trait BinanceRequestTrait
{
    use HttpRequestTrait, ResponseTrait;

    protected $spotHost = 'https://api.binance.com';

    protected $futureHost = 'https://fapi.binance.com';


    protected $key;

    protected $secret;

    protected $checked = true;

    public function keySecret($key, $secret)
    {

        $this->key = $key;
        $this->secret = $secret;

        return $this;
    }

    /**
     * @throws GuzzleException
     */
    protected function send($format = true)
    {

        $this->options['headers'] = ['X-MBX-APIKEY' => $this->key];
        $this->options['timeout'] = 60;

        if ($this->checked) {
            $this->body = array_merge($this->body, [
                'timestamp'  => time() . '000',
                'recvWindow' => config('binance.recvWindow', 5000)
            ]);
        }

        $this->checked = true;

        $query = http_build_query($this->body, '', '&');

        if (!empty($this->secret)) {
            $query = $query . '&signature=' . hash_hmac('sha256', $query, $this->secret);
        }

        $this->url = $this->url . '?' . $query;

        try {
            $data = json_decode($this->request(), true);

            return $format ? $this->response($data) : $data;
        } catch (RequestException $e) {
            if (method_exists($e->getResponse(), 'getBody')) {
                $contents = $e->getResponse()->getBody()->getContents();

                $temp = json_decode($contents, true);
                if (!empty($temp)) {
                    return $this->error($temp['msg'], $temp['code']);
                }
            }

            return $this->error('Server error:' . $e->getCode());
        }
    }

}
