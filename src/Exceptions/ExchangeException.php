<?php

namespace Aoeng\Laravel\Exchange\Exceptions;

use Exception;

class ExchangeException extends Exception
{
    /**
     * @var array
     */
    public $raw = [];

    /**
     * GatewayErrorException constructor.
     *
     * @param string $message
     * @param int $code
     * @param array $raw
     */
    public function __construct($message, $code, array $raw = [])
    {
        parent::__construct($message, intval($code));
        $this->raw = $raw;
    }
}
