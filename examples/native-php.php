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
    'notify_url' => 'https://your-domain.com/payment/huifu/notify',
    'prod_mode' => true,
]);

try {
    $basicEntry = $huifu->entry()->basicOpenIndividual([
        'name' => '测试用户',
        'cert_no' => '3301xxxxxxxxxxxx',
        'mobile_no' => '13800000000',
    ]);

    $busiOpen = $huifu->entry()->openBusiness($basicEntry['huifu_id'], [
        'settle_config' => [
            'settle_cycle' => 'T1',
        ],
        'cash_config' => [
            ['cash_type' => 'T1', 'fix_amt' => '0.00'],
        ],
    ]);

    $pay = $huifu->pay()->miniApp([
        'amount' => 0.01,
        'goods_desc' => '订单支付',
        'order_no' => 'M202603120001',
        'sub_appid' => 'wx1234567890abcdef',
        'sub_openid' => 'oUpF8uMuAJO_M2pxb1Q9zNjWeS6o',
        'delay_acct_flag' => 'Y',
        'acct_split_bunch' => [
            'acct_infos' => [
                [
                    'div_amt' => '0.0030',
                    'huifu_id' => '666600010000002',
                ],
                [
                    'div_amt' => '0.0020',
                    'huifu_id' => '666600010000003',
                ],
            ],
        ],
    ]);

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

    $splitConfirm = $huifu->split()->confirm([
        'org_req_seq_id' => 'rQ202603120001',
        'org_req_date' => '20260312',
        'acct_split_bunch' => [
            'acct_infos' => [
                [
                    'div_amt' => '0.0030',
                    'huifu_id' => '666600010000002',
                ],
            ],
        ],
    ]);

    $splitConfirmQuery = $huifu->split()->confirmQuery([
        'org_req_seq_id' => $splitConfirm['req_seq_id'],
        'org_req_date' => $splitConfirm['req_date'],
    ]);

    var_dump($basicEntry, $busiOpen, $pay, $payout, $refund, $splitConfirm, $splitConfirmQuery);
} catch (EasyHuifuException $e) {
    echo $e->getMessage() . PHP_EOL;
}
