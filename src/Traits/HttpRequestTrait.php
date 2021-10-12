<?php

namespace Aoeng\Laravel\Exchange\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Http;

trait HttpRequestTrait
{

    private $url = '';

    private $method = 'GET';

    private $body = [];

    private $options = [];


    /**
     * @throws GuzzleException
     */
    public function request()
    {
        $client = new Client();

        if (config('exchange.proxy', null)) {
            $this->options = array_merge([
                'proxy'  => config('exchange.proxy', null),
                'verify' => false
            ], $this->options);
        }

        $response = $client->request($this->method, $this->url, $this->options);

        return $response->getBody()->getContents();
    }
}
