<?php

require __DIR__ . '/../vendor/autoload.php';

use EasyHuifu\Application;
use EasyHuifu\Exception\EasyHuifuException;

$huifu = new Application([
    'sys_id' => '666600010000001', // 汇付系统号
    'product_id' => '1234567890', // 产品号
    'rsa_private_key' => 'your-private-key', // 商户私钥
    'rsa_public_key' => 'huifu-public-key', // 汇付公钥
    'upper_huifu_id' => '666600010000001', // 上级汇付号
    'notify_url' => 'https://your-domain.com/payment/huifu/notify', // 默认支付回调地址
    'prod_mode' => true, // 是否生产模式
]);

try {
    // 第一步：仅进件（个人）
    $basicEntry = $huifu->entry()->basicOpenIndividual([
        'name' => '测试用户', // 姓名
        'cert_no' => '3301xxxxxxxxxxxx', // 证件号
        'mobile_no' => '13800000000', // 手机号
    ]);

    // 第二步：业务入驻（可按需补充结算参数）
    $busiOpen = $huifu->entry()->openBusiness($basicEntry['huifu_id'], [
        'settle_config' => [
            'settle_cycle' => 'T1', // 结算周期
        ],
        'cash_config' => [
            ['cash_type' => 'T1', 'fix_amt' => '0.00'], // 默认 T1 提现配置
        ],
    ]);

    // 支付下单（含延迟分账参数）
    $pay = $huifu->pay()->miniApp([
        'amount' => 0.01, // 支付金额（元）
        'goods_desc' => '订单支付', // 商品描述
        'order_no' => 'M202603120001', // 业务订单号
        'sub_appid' => 'wx1234567890abcdef', // 微信子应用 appid
        'sub_openid' => 'oUpF8uMuAJO_M2pxb1Q9zNjWeS6o', // 用户 openid
        'delay_acct_flag' => 'Y', // 开启延迟分账
        'acct_split_bunch' => [
            'acct_infos' => [
                [
                    'div_amt' => '0.0030', // 分账金额
                    'huifu_id' => '666600010000002', // 分账接收方汇付号
                ],
                [
                    'div_amt' => '0.0020', // 分账金额
                    'huifu_id' => '666600010000003', // 分账接收方汇付号
                ],
            ],
        ],
    ]);

    // 微信 APP 支付
    $appPay = $huifu->pay()->app([
        'amount' => 0.01, // 支付金额（元）
        'goods_desc' => '订单支付', // 商品描述
        'order_no' => 'M202603120002', // 业务订单号
        'sub_appid' => 'wx1234567890abcdef', // 微信应用 appid
    ]);

    // 支付宝 APP 拉起
    $alipayApp = $huifu->pay()->alipayApp([
        'amount' => 0.01, // 支付金额（元）
        'goods_desc' => '订单支付', // 商品描述
        'order_no' => 'M202603120003', // 业务订单号
        'subject' => '订单支付', // 支付宝标题
    ]);

    // 余额打款
    $payout = $huifu->payout()->payToActor([
        'huifu_id' => '6666000xxxxxxx', // 收款方汇付号
        'amount' => 12.50, // 打款金额（元）
        'remark' => '用户提现打款', // 业务备注
    ]);

    // 退款
    $refund = $huifu->refund()->scanPay([
        'huifu_req_seq_id' => 'rQ202603120001', // 原支付请求流水号
        'transaction_id' => '0036000123456789', // 原支付全局流水号
        'pay_time' => time(), // 原支付时间
    ], 10.00, 'refund202603120001'); // 退款金额、退款单号

    // 交易确认（把延迟户资金划转给分账接收方）
    $splitConfirm = $huifu->split()->confirm([
        'org_req_seq_id' => 'rQ202603120001', // 原支付请求流水号
        'org_req_date' => '20260312', // 原支付请求日期
        'acct_split_bunch' => [
            'acct_infos' => [
                [
                    'div_amt' => '0.0030', // 分账金额
                    'huifu_id' => '666600010000002', // 分账接收方汇付号
                ],
            ],
        ],
    ]);

    // 交易确认查询
    $splitConfirmQuery = $huifu->split()->confirmQuery([
        'org_req_seq_id' => $splitConfirm['req_seq_id'], // 交易确认请求流水号
        'org_req_date' => $splitConfirm['req_date'], // 交易确认请求日期
    ]);

    var_dump($basicEntry, $busiOpen, $pay, $appPay, $alipayApp, $payout, $refund, $splitConfirm, $splitConfirmQuery);
} catch (EasyHuifuException $e) {
    echo $e->getMessage() . PHP_EOL;
}
