<?php

require __DIR__ . '/../vendor/autoload.php';

use EasyHuifu\Application;
use EasyHuifu\Exception\EasyHuifuException;

$huifu = new Application([
    'sys_id' => '666600010000001',
    'product_id' => '1234567890',
    'rsa_private_key' => 'your-private-key',
    'rsa_public_key' => 'huifu-public-key',
    'upper_huifu_id' => '666600010000001',
    'prod_mode' => true,
]);

try {
    $payout = $huifu->payout()->payToActor([
        'huifu_id' => '6666000xxxxxxx',
        'amount' => 12.50,
        'remark' => '用户提现打款',
    ]);

    $refund = $huifu->refund()->scanPay([
        'huifu_req_seq_id' => 'rQ202603120001',
        'transaction_id' => '0036000123456789',
        'pay_time' => time(),
    ], 10.00, 'refund202603120001');

    var_dump($payout, $refund);
} catch (EasyHuifuException $e) {
    echo $e->getMessage() . PHP_EOL;
}
