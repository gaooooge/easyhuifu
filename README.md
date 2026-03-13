# easyhuifu

`easyhuifu` 是一个面向 PHP 的汇付能力封装包，用于统一管理配置、SDK 初始化、请求发送、响应解析及异常处理。

## 功能概览

- 交易支付下单
- 支付查询
- 支付关单
- 余额打款
- 扫码退款
- 个人进件
- 企业进件
- 业务开通
- 进件回显
- 进件档案快照持久化
- 联行号字典查询
- 汇付地区编码查询

## 目录结构

- `src/Application.php`
  统一入口，负责组装配置、工厂和业务服务
- `src/Config.php`
  配置读取封装
- `src/bootstrap.php`
  汇付 SDK 自动加载兼容处理
- `src/Foundation/BsPayClientFactory.php`
  SDK 初始化及客户端工厂
- `src/Service/PayService.php`
  交易支付服务
- `src/Service/PayoutService.php`
  余额打款服务
- `src/Service/RefundService.php`
  退款服务
- `src/Service/EntryService.php`
  进件与业务开通服务
- `src/Contracts/*`
  扩展接口定义
- `src/Support/*`
  默认支持组件与本地字典仓储
- `data/*`
  联行号与地区编码参考数据
- `docs/THINKPHP_ADAPTER.md`
  ThinkPHP 接入说明
- `examples/*`
  可复制示例

## 安装

### Composer

```bash
composer require gaooooge/easyhuifu
```

### VCS 仓库

如未接入 Packagist，可在目标项目 `composer.json` 中增加：

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/gaooooge/easyhuifu"
    }
  ],
  "require": {
    "gaooooge/easyhuifu": "^0.1"
  }
}
```

然后执行：

```bash
composer update gaooooge/easyhuifu
```

### Path 仓库

用于本地联调：

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../easyhuifu"
    }
  ],
  "require": {
    "gaooooge/easyhuifu": "*"
  }
}
```

## 初始化

```php
use EasyHuifu\Application;

$huifu = new Application([
    'sys_id' => '666600010000001',
    'product_id' => '1234567890',
    'rsa_private_key' => 'your-private-key',
    'rsa_public_key' => 'huifu-public-key',
    'upper_huifu_id' => '666600010000001',
    'notify_url' => 'https://your-domain.com/payment/huifu/notify',
    'prod_mode' => true,
]);
```

## 服务入口

```php
$huifu->pay();
$huifu->payout();
$huifu->refund();
$huifu->split();
$huifu->entry();
$huifu->bankBranches();
$huifu->regions();
```

## 配置说明

### 必填配置

- `sys_id`
  汇付系统号
- `product_id`
  产品号
- `rsa_private_key`
  商户私钥
- `rsa_public_key`
  汇付公钥

### 可选配置

- `upper_huifu_id`
  上级汇付号；未传时默认回退 `sys_id`
- `merchant_key`
  SDK 内部实例标识；未传时自动生成
- `prod_mode`
  是否生产环境，默认 `true`
- `notify_url`
  默认支付回调地址
- `ljh_data`
  业务开通附加配置
- `hxy_data`
  业务开通附加配置

## 支付

### 支付下单

```php
$pay = $huifu->pay()->miniApp([
    'amount' => 0.01,
    'goods_desc' => '订单支付',
    'order_no' => 'M202603120001',
    'notify_url' => 'https://your-domain.com/payment/huifu/notify',
    'sub_appid' => 'wx1234567890abcdef',
    'sub_openid' => 'oUpF8uMuAJO_M2pxb1Q9zNjWeS6o',
]);
```

也可以使用：

```php
$huifu->pay()->create([...]);
$huifu->pay()->jsPay([...]);
```

### 返回结果示例

```php
[
    'req_seq_id' => 'rQ20260312123000123456789012345678',
    'req_date' => '20260312',
    'huifu_id' => '666600010000001',
    'resp_code' => '00000100',
    'resp_desc' => '成功',
    'pay_info' => [
        'package' => 'prepay_id=wx...',
        'timeStamp' => '1710211200',
        'nonceStr' => 'abc123',
        'signType' => 'RSA',
        'paySign' => 'xxxx',
    ],
    'response' => [...],
]
```

### 支付参数

- `amount`
  必填，支付金额
- `notify_url`
  必填；未传时读取初始化配置中的默认值
- `goods_desc`
  可选，商品描述，默认 `订单支付`
- `order_no`
  可选，业务订单号；默认透传到 `remark`
- `req_seq_id`
  可选，自定义请求流水号
- `huifu_id`
  可选，默认使用配置中的 `sys_id`
- `trade_type`
  可选；不传时根据 `pay_source` 推断
- `pay_source`
  可选；`wx/wxapp/miniapp` 映射为 `T_MINIAPP`，`mp/jsapi` 映射为 `T_JSAPI`
- `sub_appid`
  微信子应用 `appid`
- `sub_openid`
  微信子应用下用户 `openid`
- `wx_data`
  可选，微信参数集合；支持数组或 JSON 字符串
- `delay_acct_flag`
  可选，是否延迟分账，`Y` 为开启，默认 `N`
- `acct_split_bunch`
  可选，延迟分账明细；支持数组或 JSON 字符串

### 延迟分账示例

```php
$pay = $huifu->pay()->miniApp([
    'amount' => 0.01,
    'goods_desc' => '订单支付',
    'order_no' => 'M202603120001',
    'notify_url' => 'https://your-domain.com/payment/huifu/notify',
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
```

说明：

- `delay_acct_flag = Y` 表示按延迟分账处理
- `acct_split_bunch.acct_infos[*].huifu_id` 为分账接收方汇付号
- `acct_split_bunch.acct_infos[*].div_amt` 为预分账金额
- 该参数仅随支付请求一并提交，不代表最终分账已执行

### 支付查询

```php
$query = $huifu->pay()->query([
    'org_req_seq_id' => 'rQ20260312123000123456789012345678',
    'org_req_date' => '20260312',
]);
```

可用于查询的原始标识：

- `out_ord_id`
- `org_hf_seq_id`
- `org_req_seq_id`

### 支付关单

```php
$close = $huifu->pay()->close([
    'org_req_seq_id' => 'rQ20260312123000123456789012345678',
    'org_req_date' => '20260312',
]);
```

## 打款

```php
$result = $huifu->payout()->payToActor([
    'role_type' => 'supplier',
    'actor_id' => 20001,
    'app_id' => 10000,
    'amount' => 88.00,
    'remark' => '供应商提现打款',
]);
```

### 打款参数

- `amount`
  必填，打款金额
- `remark`
  可选，备注
- `huifu_id`
  可选；直接指定收款主体汇付号
- `role_type`
  可选；未传 `huifu_id` 时建议传入
- `actor_id`
  可选；未传 `huifu_id` 时建议传入
- `app_id`
  可选，多应用场景下用于优先匹配进件档案
- `out_huifu_id`
  可选，默认使用 `sys_id`

## 退款

```php
$result = $huifu->refund()->scanPay([
    'huifu_req_seq_id' => 'rQ202603120001',
    'transaction_id' => '0036000123456789',
    'pay_time' => time(),
], 10.00, 'refund202603120001');
```

别名方法：

```php
$result = $huifu->refund()->refund($order, 10.00, 'refund202603120001');
```

## 延迟分账确认

```php
$confirm = $huifu->split()->confirm([
    'huifu_id' => '666600010000001',
    'org_req_seq_id' => 'rQ20260312123000123456789012345678',
    'org_req_date' => '20260312',
    'pay_type' => 'ACCT_PAYMENT',
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
    'remark' => 'order split confirm',
]);
```

## 延迟分账确认查询

```php
$query = $huifu->split()->confirmQuery([
    'huifu_id' => '666600010000001',
    'org_req_seq_id' => 'rS20260312123500123456789012345678',
    'org_req_date' => '20260312',
]);
```

## 延迟分账确认退款

```php
$refundConfirm = $huifu->split()->confirmRefund([
    'huifu_id' => '666600010000001',
    'org_req_seq_id' => 'rS20260312123500123456789012345678',
    'org_req_date' => '20260312',
    'acct_split_bunch' => [
        'acct_infos' => [
            [
                'div_amt' => '0.0010',
                'huifu_id' => '666600010000002',
            ],
        ],
    ],
    'remark' => 'split confirm refund',
]);
```

## 进件

### 个人进件

```php
$result = $huifu->entry()->openIndividual([
    'name' => '测试用户',
    'cert_no' => '3301xxxxxxxxxxxx',
    'mobile_no' => '13800000000',
    'cert_type' => '00',
    'card_no' => '6222xxxxxxxxxxxx',
    'prov_id' => '310000',
    'area_id' => '310100',
    'bank_code' => '01050000',
    'branch_code' => '105290071008',
]);
```

### 企业进件

```php
$result = $huifu->entry()->openEnterprise([
    'reg_name' => '测试企业',
    'license_code' => '9131xxxxxxxxxxxx',
    'contact_name' => '张三',
    'contact_mobile' => '13800000000',
    'legal_name' => '张三',
    'legal_cert_no' => '3301xxxxxxxxxxxx',
    'bank_code' => '01050000',
    'branch_name' => '中国建设银行上海张江支行',
]);
```

说明：

- 企业银行卡场景可直接传 `branch_code`
- 仅传 `branch_name` 时，默认使用本地联行号字典解析
- 如需覆盖默认解析逻辑，可注入 `branch_code_resolver`

### 业务开通

```php
$result = $huifu->entry()->openBusiness('6666000xxxxxxx', [
    'fee_rate' => '0.38',
]);
```

### 进件回显

```php
$detail = $huifu->entry()->detailByActor([
    'role_type' => 'supplier',
    'actor_id' => 20001,
    'app_id' => 10000,
]);
```

## 联行号字典

```php
$banks = $huifu->bankBranches()->getBankOptions('建设');

$branches = $huifu->bankBranches()->getBranchList([
    'head_bank_code' => '01050000',
    'keyword' => '上海',
    'page' => 1,
    'pageSize' => 20,
]);

$branchCode = $huifu->bankBranches()->resolveBranchCode('中国建设银行上海张江支行', '01050000');
$isValid = $huifu->bankBranches()->isValidBranchCode($branchCode);
$branch = $huifu->bankBranches()->getByUnionCode($branchCode);
```

### 常用方法

- `getBankOptions($keyword = '', $limit = 200)`
  查询银行选项
- `getBranchList(array $params = [])`
  分页查询支行列表
- `resolveBranchCode($branchName, $bankCode = '')`
  通过支行名称获取联行号
- `isValidBranchCode($unionCode)`
  校验联行号是否存在
- `matchBranches($keyword, $bankCode = '', $limit = 20)`
  模糊搜索支行
- `getByUnionCode($unionCode)`
  根据联行号反查支行信息

说明：

- 若同名支行命中多条且未传 `bankCode`，`resolveBranchCode()` 返回空字符串
- 该行为用于避免歧义匹配

## 地区编码

```php
$tree = $huifu->regions()->tree();
$provinceList = $huifu->regions()->getChildren('');
$cityList = $huifu->regions()->getChildren('310000');
$districtCode = $huifu->regions()->getCodeByName('浦东新区', 3, '310100');
```

## 适配器机制

支持通过第二个构造参数注入扩展服务：

```php
$huifu = new Application($config, [
    'logger' => $logger,
    'entry_repository' => $entryRepository,
    'branch_code_resolver' => $branchCodeResolver,
    'region_repository' => $regionRepository,
]);
```

### 可注入服务

- `logger`
  自定义日志实现
- `entry_repository`
  自定义进件档案仓储
- `branch_code_resolver`
  自定义支行名称转联行号解析器
- `region_repository`
  自定义地区编码仓储

## 异常处理

所有服务统一抛出：

```php
EasyHuifu\Exception\EasyHuifuException
```

建议在业务层统一捕获并转换为自身的异常类型或错误响应。

## 参考数据维护

`scripts/export_reference_data.php` 用于维护 `data/` 目录下的参考数据文件，不属于运行时依赖。

导出联行号数据时需显式提供环境变量：

- `EASYHUIFU_DB_HOST`
- `EASYHUIFU_DB_NAME`
- `EASYHUIFU_DB_USER`
- `EASYHUIFU_DB_PASS`
- `EASYHUIFU_DB_PORT`
- `EASYHUIFU_DB_PREFIX`

## 示例

请参考：

- `examples/native-php.php`
- `examples/README.md`
- `docs/THINKPHP_ADAPTER.md`

## 进件与入驻拆分说明

`EntryService` 现在提供两套调用方式：

- 仅进件（不要求银行卡参数）
  - `entry()->basicOpenIndividual([...])`
  - `entry()->basicOpenEnterprise([...])`
- 进件+入驻（一体化，保持兼容）
  - `entry()->openIndividual([...])`
  - `entry()->openEnterprise([...])`

说明：

- `basicOpen*` 只做基础进件，返回 `huifu_id` 和 `basic_open`。
- `open*` 会先调用 `basicOpen*`，再调用 `openBusiness` 完成入驻。
- 入驻阶段若涉及结算卡/代发配置，仍按 `openBusiness` 参数规则传入（可在业务侧按需补充银行卡信息）。

### 仅进件示例（个人）

```php
$basic = $huifu->entry()->basicOpenIndividual([
    'name' => '测试用户',
    'cert_no' => '3301xxxxxxxxxxxx',
    'mobile_no' => '13800000000',
]);
```

### 仅进件示例（企业）

```php
$basic = $huifu->entry()->basicOpenEnterprise([
    'reg_name' => '测试企业',
    'license_code' => '9131xxxxxxxxxxxx',
    'reg_prov_id' => '310000',
    'reg_area_id' => '310100',
    'reg_district_id' => '310115',
    'reg_detail' => '浦东新区xx路xx号',
    'legal_name' => '张三',
    'legal_cert_no' => '3301xxxxxxxxxxxx',
    'contact_name' => '张三',
    'contact_mobile' => '13800000000',
]);
```

### 分步入驻示例（先进件后入驻）

```php
$basic = $huifu->entry()->basicOpenIndividual([
    'name' => '测试用户',
    'cert_no' => '3301xxxxxxxxxxxx',
    'mobile_no' => '13800000000',
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


## 延迟分账交易确认说明（必读）

- 开启 `delay_acct_flag = Y` 后，支付资金先进入延迟户，`pay()->miniApp()/jsPay()` 仅是“预分账”，不会自动划转到分账接收方。
- 订单支付成功后，需要主动调用交易确认接口完成划转：`$huifu->split()->confirm([...])`。
- 确认提交后可调用 `$huifu->split()->confirmQuery([...])` 查询确认状态，建议在业务侧做重试与幂等。

接口映射（easyhuifu -> 汇付官方 SDK）：
- `split()->confirm()` -> `V2TradePaymentDelaytransConfirmRequest`
- `split()->confirmQuery()` -> `V2TradePaymentDelaytransConfirmqueryRequest`
- `split()->confirmRefund()` -> `V2TradePaymentDelaytransConfirmrefundRequest`

当前状态：
- 以上 3 个接口已经在 `easyhuifu` 中封装完成。
- `V2TradePaymentDelaytransConfirmrefundqueryRequest`（确认退款查询）已封装，对应 `split()->confirmRefundQuery()`。
