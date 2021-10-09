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

    private $options = [
        'proxy'  => [
            'https' => '192.168.10.1:1080',
            'http'  => '192.168.10.1:1080',
        ],
        'verify' => false
    ];

    /**
     * @throws GuzzleException
     */
    public function request()
    {
        $client = new Client();

        $response = $client->request($this->method, $this->url, $this->options);

        return $response->getBody()->getContents();
    }
}
