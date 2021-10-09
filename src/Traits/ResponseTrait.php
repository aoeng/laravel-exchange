<?php

namespace Aoeng\Laravel\Exchange\Traits;

trait ResponseTrait
{
    public function response($data)
    {
        return ['code' => 0, 'data' => $data];
    }


    public function error($message, $code = -1, $data = [])
    {
        return ['code' => $code, 'message' => $message, 'data' => $data];
    }

}
