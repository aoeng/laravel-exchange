<?php

namespace Aoeng\Laravel\Exchange\Adapters;

use Aoeng\Laravel\Exchange\Facades\Exchange;
use Illuminate\Support\Str;

class Binance
{

    public static $transferMap = [
        Exchange::TRANSFER_SPOT2SWAP => 'MAIN_UMFUTURE',
        Exchange::TRANSFER_SWAP2SPOT => 'UMFUTURE_MAIN',
    ];

    public static $positionModeMap = [
        Exchange::POSITION_MODE_DOUBLE => 'true',
        Exchange::POSITION_MODE_SINGLE => 'false',
    ];

    public static $positionSideMap = [
        Exchange::POSITION_SIDE_BOTH  => 'BOTH',
        Exchange::POSITION_SIDE_LONG  => 'LONG',
        Exchange::POSITION_SIDE_SHORT => 'SHORT',
    ];

    public static $positionTypeMap = [
        Exchange::POSITION_TYPE_CROSSED  => 'CROSSED',
        Exchange::POSITION_TYPE_ISOLATED => 'ISOLATED',
    ];

    public static $orderSideMap = [
        Exchange::ORDER_SIDE_BUY  => 'BUY',
        Exchange::ORDER_SIDE_SELL => 'SELL',
    ];

    public static $orderTypeMap = [
        Exchange::ORDER_TYPE_MARKET   => 'MARKET',
        Exchange::ORDER_TYPE_LIMIT    => 'LIMIT',
        Exchange::ORDER_TYPE_TRAILING => 'TRAILING_STOP_MARKET',
    ];

    public static $marginChangeMap = [
        Exchange::MARGIN_CHANGE_ADD => 1,
        Exchange::MARGIN_CHANGE_SUB => 2,
    ];

    public static function formatPeriod($period)
    {
        $period = Str::lower($period);

        if (Str::contains($period, 'min')) {
            return Str::replace('min', 'm', $period);
        }

        if (Str::contains($period, 'hour')) {
            return Str::replace('hour', 'h', $period);
        }

        if (Str::contains($period, 'day')) {
            return Str::replace('day', 'd', $period);
        }

        if (Str::contains($period, 'week')) {
            return Str::replace('week', 'w', $period);
        }

        if (Str::contains($period, 'mon')) {
            return Str::replace('mon', 'm', $period);
        }

        if (Str::contains($period, 'year')) {
            return Str::replace('year', 'y', $period);
        }

        return $period;
    }

    public static function formatSymbol($spotSymbol = [], $contractSymbol = false)
    {
        $symbol = [
            'symbol'            => $spotSymbol['symbol'] ?? null,
            'baseCurrency'      => $spotSymbol['baseAsset'] ?? null,
            'quoteCurrency'     => $spotSymbol['quoteAsset'] ?? null,
            'pricePrecision'    => $spotSymbol['pricePrecision'] ?? null,
            'quantityPrecision' => $spotSymbol['quantityPrecision'] ?? null,
            'contractSize'      => 0,
            'status'            => $spotSymbol['status'] == 'TRADING',
            'extra'             => ['spot' => $spotSymbol, 'contract' => $contractSymbol,],
        ];
        if ($contractSymbol) {
            $symbol['contractSymbol'] = $contractSymbol['symbol'];
        }

        return $symbol;
    }

    public static function formatPosition($position, $risk = [])
    {
        $positionSideMap = array_flip(self::$positionSideMap);

        return [
            'symbol'         => $position['symbol'],
            'positionType'   => $position['isolated'] ? Exchange::POSITION_TYPE_ISOLATED : Exchange::POSITION_TYPE_CROSSED,
            'positionSide'   => $positionSideMap[$position['positionSide']],
            'lever'          => $position['leverage'],
            'volume'         => $position['positionAmt'],
            'openPrice'      => $position['entryPrice'],
            'initialMargin'  => $position['initialMargin'],
            'maintainMargin' => $position['maintMargin'],
            'profit'         => $position['unrealizedProfit'],
            'maxAmount'      => $position['maxNotional'],
            'maxVolume'      => 0,
            'blastPrice'     => $risk['liquidationPrice'] ?? 0,
        ];
    }

    public static function formatOrder($order)
    {
        return [
            'orderId'       => $order['orderId'],
            'clientOrderId' => $order['clientOrderId'],
            'detail'        => $order,
        ];
    }
}
