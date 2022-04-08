<?php

namespace Aoeng\Laravel\Exchange\Traits;


trait SymbolTrait
{

    public $symbol;

    public $contractSymbol = false;

    public $baseCurrency;

    public $quoteCurrency;

    public $pricePrecision;

    public $quantityPrecision;

    public $contractSize;

    public $status = true;

    public $extra;


    public function raw()
    {
        return [
            'symbol'            => $this->symbol,
            'contractSymbol'    => $this->contractSymbol,
            'baseCurrency'      => $this->baseCurrency,
            'quoteCurrency'     => $this->quoteCurrency,
            'pricePrecision'    => $this->pricePrecision,
            'quantityPrecision' => $this->quantityPrecision,
            'contractSize'      => $this->contractSize,
            'status'            => $this->status,
            'extra'             => $this->extra,
        ];
    }
}
