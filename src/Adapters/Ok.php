<?php

namespace Aoeng\Laravel\Exchange\Adapters;

use Aoeng\Laravel\Exchange\Facades\Exchange;
use Illuminate\Support\Str;

class Ok
{
    public static $envMap = [
        Exchange::ENV_SPOT => 'SPOT',
        Exchange::ENV_SWAP => 'SWAP',
    ];


    public static $positionModeMap = [
        Exchange::POSITION_MODE_DOUBLE => 'long_short_mode',
        Exchange::POSITION_MODE_SINGLE => 'net_mode',
    ];

    public static $positionSideMap = [
        Exchange::POSITION_SIDE_BOTH  => 'net',
        Exchange::POSITION_SIDE_LONG  => 'long',
        Exchange::POSITION_SIDE_SHORT => 'short',
    ];

    public static $positionTypeMap = [
        Exchange::POSITION_TYPE_CASH     => 'cash',
        Exchange::POSITION_TYPE_CROSSED  => 'cross',
        Exchange::POSITION_TYPE_ISOLATED => 'isolated',
    ];

    public static $orderSideMap = [
        Exchange::ORDER_SIDE_BUY  => 'buy',
        Exchange::ORDER_SIDE_SELL => 'sell',
    ];

    public static $orderTypeMap = [
        Exchange::ORDER_TYPE_MARKET   => 'market',
        Exchange::ORDER_TYPE_LIMIT    => 'limit',
        Exchange::ORDER_TYPE_TRAILING => 'trailing',
    ];

    public static $orderQuantityTypeMap = [
        Exchange::ORDER_QUANTITY_TYPE_BASE  => 'base_ccy',
        Exchange::ORDER_QUANTITY_TYPE_QUITE => 'quote_ccy',
    ];

    public static $marginChangeMap = [
        Exchange::MARGIN_CHANGE_ADD => 'add',
        Exchange::MARGIN_CHANGE_SUB => 'reduce',
    ];

    public static function formatPeriod($period)
    {
        if (Str::contains($period, 'min')) {
            return Str::replace('min', 'm', $period);
        }

        if (Str::contains($period, 'm') && !Str::contains($period, 'mon')) {
            return $period;
        }

        $period = Str::upper($period);

        if (Str::contains($period, 'HOUR')) {
            return Str::replace('HOUR', 'H', $period);
        }

        if (Str::contains($period, 'DAY')) {
            return Str::replace('DAY', 'D', $period);
        }

        if (Str::contains($period, 'WEEK')) {
            return Str::replace('WEEK', 'W', $period);
        }

        if (Str::contains($period, 'MON')) {
            return Str::replace('MON', 'M', $period);
        }

        if (Str::contains($period, 'YEAR')) {
            return Str::replace('YEAR', 'Y', $period);
        }

        return $period;
    }

    public static function formatSymbol($spotSymbol = [], $contractSymbol = false)
    {
        $symbol = [
            'symbol'            => $spotSymbol['instId'] ?? null,
            'baseCurrency'      => $spotSymbol['baseCcy'] ?? null,
            'quoteCurrency'     => $spotSymbol['quoteCcy'] ?? null,
            'pricePrecision'    => intval($contractSymbol ? log($contractSymbol['tickSz'], 0.1) : log($spotSymbol['tickSz'], 0.1)),
            'quantityPrecision' => intval(log($spotSymbol['lotSz'], 0.1)),
            'contractSize'      => $contractSymbol ? $contractSymbol['ctVal'] : 0,
            'status'            => $spotSymbol['state'] == 'live',
            'extra'             => ['spot' => $spotSymbol, 'contract' => $contractSymbol,],
        ];
        if ($contractSymbol) {
            $symbol['contractSymbol'] = $contractSymbol['instId'];
        }

        return $symbol;
    }

    public static function formatPosition($position, $maxSize = [])
    {
        $positionSideMap = array_flip(self::$positionSideMap);
        $positionTypeMap = array_flip(self::$positionTypeMap);

        return [
            'symbol'        => $position['instId'],
            'positionType'  => $positionTypeMap[$position['mgnMode']],
            'positionSide'  => $positionSideMap[$position['posSide']],
            'lever'         => $position['lever'],
            'volume'        => $position['pos'],
            'openPrice'     => $position['avgPx'],
            'initialMargin' => $position['imr'],
            'KeepMargin'    => $position['mmr'],
            'profit'        => $position['upl'],
            'maxAmount'     => 0,
            'maxVolume'     => $positionSideMap[$position['posSide']] == Exchange::POSITION_SIDE_LONG ? $maxSize['maxBuy'] ?? 0 : $maxSize['maxSell'] ?? 0,
            'blastPrice'    => $position['liqPx'],
        ];
    }

    public static function formatOrder($order)
    {
        return [
            'orderId'       => $order['ordId'],
            'clientOrderId' => $order['clOrdId'],
            'detail'        => $order,
        ];
    }
}
