<?php

namespace Aoeng\Laravel\Exchange\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

trait HttpRequestTrait
{

    private $url = '';

    private $method = 'GET';

    private $body = [];

    private $options = [];

    private $proxy = [];


    /**
     * @throws GuzzleException
     */
    public function request()
    {
        $client = new Client();

        if (!empty($this->proxy)) {
            $this->options = array_merge([
                'proxy'  => $this->proxy,
                'verify' => false
            ], $this->options);
        }

        $response = $client->request($this->method, $this->url, $this->options);

        return $response->getBody()->getContents();
    }
}
