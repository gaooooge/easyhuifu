<?php

namespace EasyHuifu\Support;

use EasyHuifu\Contracts\LoggerInterface;

class NullLogger implements LoggerInterface
{
    public function info($event, array $context = [])
    {
    }

    public function error($event, array $context = [])
    {
    }
}
