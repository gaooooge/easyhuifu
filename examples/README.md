# examples

本目录提供可直接复制的示例文件，用于说明 `easyhuifu` 的基础接入方式。

## 文件说明

- `composer-vcs-repository.json`
  VCS 仓库安装示例。
- `composer-path-repository.json`
  本地 Path 仓库安装示例。
- `native-php.php`
  原生 PHP 初始化与调用示例，包含支付、打款、退款、分账确认。
- `thinkphp/HuifuApplicationFactory.php`
  ThinkPHP 工厂示例。
- `thinkphp/ThinkHuifuLogger.php`
  ThinkPHP 日志适配器示例。
- `thinkphp/ThinkHuifuEntryRepository.php`
  ThinkPHP 进件仓储适配器示例。
- `thinkphp/ThinkHuifuBranchCodeResolver.php`
  ThinkPHP 联行号解析适配器示例。

## Composer 安装示例

### VCS 仓库

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/gaooooge/easyhuifu"
    }
  ],
  "require": {
    "gaooooge/easyhuifu": "^0.1.4"
  }
}
```

### Path 仓库

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../easyhuifu"
    }
  ],
  "require": {
    "gaooooge/easyhuifu": "^0.1.4"
  }
}
```

## 原生 PHP 示例

请参考：

- `native-php.php`

该示例包含：

- 小程序支付下单
- 余额打款
- 退款
- 延迟分账确认与确认查询

## ThinkPHP 示例

请参考：

- `thinkphp/HuifuApplicationFactory.php`
- `thinkphp/ThinkHuifuLogger.php`
- `thinkphp/ThinkHuifuEntryRepository.php`
- `thinkphp/ThinkHuifuBranchCodeResolver.php`

## 联行号字典示例

```php
$banks = $huifu->bankBranches()->getBankOptions('建设');
$branchCode = $huifu->bankBranches()->resolveBranchCode('中国建设银行上海某某支行', '01050000');
$valid = $huifu->bankBranches()->isValidBranchCode($branchCode);
$branchList = $huifu->bankBranches()->matchBranches('张江', '01050000', 10);
```

说明：

- `isValidBranchCode()` 用于校验联行号是否存在。
- `resolveBranchCode()` 用于根据支行名称获取联行号。
- 同名支行存在歧义时，建议同时传入 `head_bank_code`。

## 地区编码示例

```php
$tree = $huifu->regions()->tree();
$provinceList = $huifu->regions()->getChildren('');
$cityList = $huifu->regions()->getChildren('310000');
$districtCode = $huifu->regions()->getCodeByName('浦东新区', 3, '310100');
```

## 小程序延迟分账示例

```php
$pay = $huifu->pay()->miniApp([
    'amount' => 0.01, // 支付金额（元）
    'goods_desc' => '订单支付', // 商品描述
    'order_no' => 'M202603120001', // 业务订单号
    'notify_url' => 'https://your-domain.com/payment/huifu/notify', // 支付回调地址
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
```

说明：

- `delay_acct_flag` 传 `Y` 表示开启延迟分账。
- `acct_split_bunch` 支持直接传数组。

## 延迟分账确认示例

```php
$confirm = $huifu->split()->confirm([
    'org_req_seq_id' => 'rQ20260312123000123456789012345678', // 原支付请求流水号
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

$confirmQuery = $huifu->split()->confirmQuery([
    'org_req_seq_id' => $confirm['req_seq_id'],
    'org_req_date' => $confirm['req_date'],
]);
```

## 进件/入驻分离示例

```php
$basic = $huifu->entry()->basicOpenIndividual([
    'name' => '测试用户', // 姓名
    'cert_no' => '3301xxxxxxxxxxxx', // 证件号
    'mobile_no' => '13800000000', // 手机号
]);

$busi = $huifu->entry()->openBusiness($basic['huifu_id'], [
    'settle_config' => [
        'settle_cycle' => 'T1',
    ],
    'cash_config' => [
        ['cash_type' => 'T1', 'fix_amt' => '0.00'],
    ],
]);
```
