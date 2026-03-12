<?php

namespace app\common\adapter\huifu;

use EasyHuifu\Contracts\LoggerInterface;
use think\facade\Log;

class ThinkHuifuLogger implements LoggerInterface
{
    public function info($event, array $context = [])
    {
        Log::channel('huifu')->info(
            $event . ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public function error($event, array $context = [])
    {
        Log::channel('huifu')->error(
            $event . ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
