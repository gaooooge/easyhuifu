<?php

namespace EasyHuifu\Contracts;

interface LoggerInterface
{
    public function info($event, array $context = []);

    public function error($event, array $context = []);
}
