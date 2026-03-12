<?php

namespace app\common\factory;

use EasyHuifu\Application;
use app\common\adapter\huifu\ThinkHuifuBranchCodeResolver;
use app\common\adapter\huifu\ThinkHuifuEntryRepository;
use app\common\adapter\huifu\ThinkHuifuLogger;

class HuifuApplicationFactory
{
    public static function make()
    {
        return new Application([
            'sys_id' => (string)env('PAYMENT.HUIFU_SYS_ID', ''),
            'product_id' => (string)env('PAYMENT.HUIFU_PRODUCT_ID', ''),
            'rsa_private_key' => (string)env('PAYMENT.HUIFU_RSA_PRIVATE', ''),
            'rsa_public_key' => (string)env('PAYMENT.HUIFU_RSA_PUBLIC', ''),
            'upper_huifu_id' => (string)env('PAYMENT.HUIFU_USER_UPPER_HUIFU_ID', ''),
            'prod_mode' => true,
        ], [
            'logger' => new ThinkHuifuLogger(),
            'entry_repository' => new ThinkHuifuEntryRepository(),
            'branch_code_resolver' => new ThinkHuifuBranchCodeResolver(),
        ]);
    }
}
