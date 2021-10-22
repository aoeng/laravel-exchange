<?php

namespace Aoeng\Laravel\Exchange\Requests;

use Aoeng\Laravel\Exchange\Traits\HttpRequestTrait;
use Aoeng\Laravel\Exchange\Traits\ResponseTrait;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

trait OkRequestTrait
{
    use HttpRequestTrait, ResponseTrait;

    protected $host = 'https://www.okex.com';

    protected $path = '';

    protected $config = [];

    protected $key;

    protected $secret;

    protected $passphrase;

    protected $simulated = false;


    public function keySecret($key, $secret, $passphrase = null)
    {

        $this->key = $key;
        $this->secret = $secret;
        $this->passphrase = $passphrase;

        $this->config['key'] = $key;
        $this->config['secret'] = $secret;
        $this->config['passphrase'] = $passphrase;

        return $this;
    }

    /**
     * @throws GuzzleException
     */
    protected function send($format = true)
    {

        $timestamp = gmdate('Y-m-d\TH:i:s\.000\Z');


        $this->options['timeout'] = 60;

        $body = empty($this->body) ? '' : (strtoupper($this->method) == 'GET' ? ('?' . http_build_query($this->body)) : json_encode($this->body));

        $sign = base64_encode(hash_hmac('sha256', $timestamp . strtoupper($this->method) . $this->path . $body, $this->secret, true));

        $this->options['headers'] = [
            'Content-Type'         => 'application/json',
            'OK-ACCESS-KEY'        => $this->key,
            'OK-ACCESS-SIGN'       => $sign,
            'OK-ACCESS-TIMESTAMP'  => $timestamp,
            'OK-ACCESS-PASSPHRASE' => $this->passphrase,
        ];

        if ($this->simulated) {
            $this->options['headers']['x-simulated-trading'] = 1;
        }

        $this->url = $this->host . $this->path;

        if ($this->method == 'GET') {
            $this->options['query'] = $this->body;
        } else {
            $this->options['json'] = $this->body;
        }

        try {

            $data = json_decode($this->request(), true);

            if ($data['code'] != 0) {
                return $this->error($data['msg'], $data['code']);
            }
            return $format ? $this->response($data['data']) : $data['data'];
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
