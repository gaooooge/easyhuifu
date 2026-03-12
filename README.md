# easyhuifu

`easyhuifu` 是一个面向多项目复用的汇付支付轻量封装包。

它参考了 `easywechat` 的使用体验，目标是把“配置、SDK 初始化、请求发送、错误归一化、能力分层”整理成统一入口，同时避免和具体框架、ORM、日志组件、数据库模型强绑定。

简单说，这个包不是给当前项目“就地重构”用的，而是给“下一个项目直接接入汇付能力”用的。当前仓库里的业务逻辑不需要先改，后续如果要迁移，再通过适配器薄接一层即可。

## 设计目标

- 提供类似 `easywechat` 的入口方式：`$huifu->payout()`、`$huifu->refund()`、`$huifu->entry()`
- 不依赖 ThinkPHP
- 不依赖任何项目内 Model
- 不直接依赖项目内日志组件
- 把汇付 SDK 的初始化细节封装掉
- 允许不同项目通过接口适配自己的“进件档案仓储、日志、联行号解析器”
- 优先适合新项目直接复用，旧项目后续渐进迁移

## 当前已封装的能力

- 余额打款
- 扫码退款
- 个人进件
- 企业进件
- 业务开通
- 进件回显
- 进件档案快照持久化

## 为什么不直接并进 easywechat

不建议把汇付能力硬并到 `easywechat` 里面，原因很直接：

- `easywechat` 解决的是微信生态接入
- 汇付是另一家支付通道，职责边界完全不同
- 如果以后还要接支付宝、乐刷、盛付通，继续往 `easywechat` 里塞只会越来越乱

更合理的做法是：

- 保留一个独立的 `easyhuifu` 包
- 在你的业务项目里做统一的支付网关管理层
- 各通道各自维护自己的配置、异常、适配器和能力边界

## 使用风格

示例：

```php
use EasyHuifu\Application;

$huifu = new Application([
    'sys_id' => '666600010000001',
    'product_id' => '1234567890',
    'rsa_private_key' => 'your-private-key',
    'rsa_public_key' => 'huifu-public-key',
    'upper_huifu_id' => '666600010000001',
    'prod_mode' => true,
]);

$result = $huifu->payout()->payToActor([
    'role_type' => 'user',
    'actor_id' => 10001,
    'amount' => 12.50,
    'huifu_id' => '6666000xxxxxxx',
    'remark' => '用户提现打款',
]);
```

整体风格是：

- `Application` 负责管理配置和服务实例
- `payout / refund / entry` 负责按能力维度拆分
- 需要扩展时，通过构造器注入适配器，而不是把业务逻辑写死在包内

## 目录结构

- `src/Application.php`
  统一入口，负责组装配置、工厂和业务服务
- `src/Config.php`
  配置读取封装，统一管理必填配置和默认值
- `src/bootstrap.php`
  汇付官方 SDK 的 autoload 兼容处理
- `src/Foundation/BsPayClientFactory.php`
  SDK 初始化和 `BsPayClient` 实例工厂
- `src/Service/PayoutService.php`
  余额打款服务
- `src/Service/RefundService.php`
  退款服务
- `src/Service/EntryService.php`
  进件和业务开通服务
- `src/Contracts/*`
  扩展接口定义
- `src/Support/NullLogger.php`
  默认空日志实现
- `docs/THINKPHP_ADAPTER.md`
  当前 ThinkPHP 项目后续如何接适配器的说明

## 安装方式

### 方式一：已经发布到私有 Composer 仓库或 Packagist

```bash
composer require gaooooge/easyhuifu
```

### 方式二：还没单独发布，先在别的项目里本地复用

可以用 `path repository` 或 `vcs repository`。

例如目标项目 `composer.json`：

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../daolujiuyuan-php/packages/easyhuifu"
    }
  ],
  "require": {
    "gaooooge/easyhuifu": "*"
  }
}
```

然后执行：

```bash
composer update gaooooge/easyhuifu
```

如果你后面打算把它独立成一个单独仓库，再切回 `vcs` 或正式包源即可。

## 配置说明

构造 `Application` 时，第一参数是配置数组。

```php
$config = [
    'sys_id' => '666600010000001',
    'product_id' => '1234567890',
    'rsa_private_key' => 'your-private-key',
    'rsa_public_key' => 'huifu-public-key',
    'upper_huifu_id' => '666600010000001',
    'merchant_key' => 'default',
    'prod_mode' => true,
    'ljh_data' => [],
    'hxy_data' => [],
];
```

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
  上级汇付号。业务开通时如果不传，默认回退为 `sys_id`
- `merchant_key`
  SDK 内部商户实例标识。一般不填，包会自动按配置哈希生成
- `prod_mode`
  是否生产环境，默认 `true`
- `ljh_data`
  业务开通时可用的乐接活配置
- `hxy_data`
  业务开通时可用的合鑫云配置

## 为什么需要 `bootstrap.php`

汇付官方包 `huifurepo/dg-php-sdk` 本身没有提供完整、标准的 Composer autoload 定义。

这个包在 [src/bootstrap.php](src/bootstrap.php) 里做了两件事：

- 自动尝试找到 `BsPaySdk/init.php`
- 自动注册 `BsPaySdk\\` 命名空间的简单加载器

这样做的结果是：

- 新项目里不需要再手动修改根项目的 `composer.json`
- 本地 path 仓库、普通 vendor 安装、Composer loader 场景都能兼容

## 基础用法

### 1. 初始化应用

```php
use EasyHuifu\Application;

$huifu = new Application([
    'sys_id' => '666600010000001',
    'product_id' => '1234567890',
    'rsa_private_key' => 'your-private-key',
    'rsa_public_key' => 'huifu-public-key',
    'upper_huifu_id' => '666600010000001',
    'prod_mode' => true,
]);
```

### 2. 余额打款

用于“平台余额打给某个已进件主体”的场景。

```php
$result = $huifu->payout()->payToActor([
    'role_type' => 'supplier',
    'actor_id' => 20001,
    'app_id' => 10000,
    'amount' => 88.00,
    'remark' => '供应商提现打款',
]);
```

返回结果示例：

```php
[
    'req_seq_id' => 'SUPPLIACP20260312120000123456',
    'payee_huifu_id' => '6666000xxxxxxx',
    'resp_code' => '00000100',
    'resp_desc' => '成功',
    'response' => [...],
]
```

#### 余额打款参数说明

- `amount`
  必填，打款金额
- `remark`
  可选，打款备注
- `huifu_id`
  可选，如果已知收款方汇付号，可直接传，跳过仓储查询
- `role_type`
  可选但强烈建议传；当未直接传 `huifu_id` 时必填
- `actor_id`
  可选但强烈建议传；当未直接传 `huifu_id` 时必填
- `app_id`
  可选，用于多应用场景下优先匹配对应进件档案
- `out_huifu_id`
  可选，默认用配置里的 `sys_id`

#### 直接按 `huifu_id` 打款

如果别的项目自己有完整的主体汇付号，不想依赖进件档案表，可以直接这么调：

```php
$huifu->payout()->payToActor([
    'huifu_id' => '6666000xxxxxxx',
    'amount' => 88.00,
    'remark' => '直接打款',
]);
```

这种模式下，不需要注入 `entry_repository`。

### 3. 扫码退款

```php
$result = $huifu->refund()->scanPay([
    'huifu_req_seq_id' => 'rQ202603120001',
    'transaction_id' => '0036000123456789',
    'pay_time' => time(),
], 10.00, 'refund202603120001');
```

也可以调别名方法：

```php
$result = $huifu->refund()->refund($order, 10.00, 'refund202603120001');
```

#### 退款参数说明

- 第一个参数 `$order`
  原支付订单信息
- 第二个参数 `$money`
  退款金额
- 第三个参数 `$outRefundNo`
  可选，退款请求流水号；不传则自动生成

#### `$order` 支持的关键字段

- `huifu_req_seq_id`
  原汇付请求流水号，优先使用
- `req_seq_id`
  原请求流水号备用字段
- `transaction_id`
  原汇付交易流水号
- `org_req_date`
  原请求日期，可选
- `pay_time`
  支付时间戳，用于兜底推导原请求日期
- `huifu_id`
  可选，退款请求使用的汇付号；不传时默认用配置里的 `sys_id`

#### 退款请求规则

- 如果同时缺少 `huifu_req_seq_id / req_seq_id / transaction_id`，会直接抛异常
- 如果 `outRefundNo` 不传，包内会自动生成
- 如果能从原请求流水号推断日期，则优先推断，否则回退 `org_req_date`，最后回退 `pay_time`

### 4. 个人进件

```php
$result = $huifu->entry()->openIndividual([
    'name' => '测试用户',
    'cert_no' => '3301xxxxxxxxxxxx',
    'mobile_no' => '13800000000',
    'cert_type' => '00',
    'card_no' => '6222xxxxxxxxxxxx',
    'prov_id' => '310000',
    'area_id' => '310100',
], [
    'role_type' => 'user',
    'actor_id' => 10001,
    'app_id' => 10000,
]);
```

#### 个人进件最少必填字段

- `name`
- `cert_no`
- `mobile_no`

但如果后续要直接可提现，通常还应提供银行卡相关字段，例如：

- `card_no`
- `card_name`
- `prov_id`
- `area_id`
- `mp`

如果你没有单独构建 `card_info`，也可以直接平铺传，包内会自动归并。

### 5. 企业进件

```php
$result = $huifu->entry()->openEnterprise([
    'reg_name' => '测试企业有限公司',
    'license_code' => '9133xxxxxxxxxxxxxx',
    'reg_prov_id' => '310000',
    'reg_area_id' => '310100',
    'reg_district_id' => '310101',
    'reg_detail' => '某某路 100 号',
    'legal_name' => '张三',
    'legal_cert_no' => '3301xxxxxxxxxxxx',
    'contact_name' => '李四',
    'contact_mobile' => '13800000000',
    'card_no' => '6222xxxxxxxxxxxx',
    'branch_name' => '中国建设银行某某支行',
    'bank_code' => '01050000',
], [
    'role_type' => 'supplier',
    'actor_id' => 20001,
    'app_id' => 10000,
]);
```

#### 企业进件最少必填字段

- `reg_name`
- `license_code`
- `reg_prov_id`
- `reg_area_id`
- `reg_district_id`
- `reg_detail`
- `legal_name`
- `legal_cert_no`
- `contact_name`
- `contact_mobile`

#### 企业银行卡参数说明

企业场景下通常还要传：

- `card_no`
- `card_name`
- `bank_code`
- `branch_name` 或 `branch_code`

如果你只传了 `branch_name`，没有传 `branch_code`，则必须注入 `branch_code_resolver`，由项目自行把支行名称解析成联行号。

### 6. 单独业务开通

如果主体已经有 `huifu_id`，只想补做业务开通：

```php
$result = $huifu->entry()->openBusiness('6666000xxxxxxx', [
    'card_no' => '6222xxxxxxxxxxxx',
    'card_name' => '测试用户',
    'prov_id' => '310000',
    'area_id' => '310100',
], [
    'role_type' => 'user',
    'actor_id' => 10001,
    'app_id' => 10000,
]);
```

### 7. 进件回显

如果项目里接了进件仓储，可以按主体查询最近一次进件档案：

```php
$detail = $huifu->entry()->detailByActor([
    'role_type' => 'supplier',
    'actor_id' => 20001,
    'app_id' => 10000,
], 'ent');
```

如果没有注入 `entry_repository`，这个方法会直接抛异常，因为包本身不负责数据库访问。

## 适配器机制

这个包支持通过第二个构造参数注入扩展服务：

```php
$huifu = new Application($config, [
    'logger' => $logger,
    'entry_repository' => $entryRepository,
    'branch_code_resolver' => $branchCodeResolver,
]);
```

### 支持的注入项

- `logger`
  自定义日志实现
- `entry_repository`
  自定义进件档案仓储
- `branch_code_resolver`
  自定义支行名称转联行号解析器

## 接口说明

### LoggerInterface

要求实现两个方法：

```php
info($event, array $context = [])
error($event, array $context = [])
```

如果不传，默认使用空实现 `NullLogger`，即不记录日志。

### EntryRepositoryInterface

要求实现三个方法：

```php
findSuccessEntry($appId, $roleType, $actorId)
findLatestEntry(array $context, $entryType = '')
saveEntryArchive(array $data)
```

用途分别是：

- 打款前查收款主体的有效进件档案
- 进件回显时查最近一条进件记录
- 进件成功后保存快照归档

### BranchCodeResolverInterface

要求实现：

```php
resolve($branchName, $bankCode = '')
```

用途是：

- 企业银行卡只传了支行名称，没有传联行号时
- 由业务项目自行查库或调用第三方服务补全联行号

## 一些重要的内部规则

这些规则已经在包里固化，不建议在业务层重复实现一遍。

### 1. SDK 初始化只封在工厂里

`BsPay::init()` 和 `BsPay::$isProdMode` 都是全局静态状态，这对多项目复用并不友好。

当前包通过 [src/Foundation/BsPayClientFactory.php](src/Foundation/BsPayClientFactory.php) 做了统一控制：

- 根据配置生成 `merchant_key`
- 同一组配置避免重复初始化
- 每次创建 client 前确认 SDK 已经正确装配

### 2. 打款默认走固定风控参数

`PayoutService` 里固定写入：

```json
{
  "transfer_type": "04",
  "sub_product": "1"
}
```

这是从当前项目的汇付打款实现里抽出来的默认规则。

### 3. 进件结算配置固定补 `T1`

`EntryService` 会统一保证：

- `settle_config.settle_cycle = T1`
- `cash_config[*].cash_type = T1`

这是从当前项目现有逻辑中直接迁移出来的默认策略。

### 4. 银行卡信息支持“平铺字段 + card_info 混传”

也就是说你既可以这样传：

```php
[
    'card_no' => '6222...',
    'card_name' => '张三',
    'prov_id' => '310000',
    'area_id' => '310100',
]
```

也可以这样传：

```php
[
    'card_info' => [
        'card_no' => '6222...',
        'card_name' => '张三',
        'prov_id' => '310000',
        'area_id' => '310100',
    ],
]
```

包内会自动归一化。

### 5. 部分敏感字段默认脱敏记录日志

例如：

- 身份证号
- 银行卡号
- 手机号
- 密钥类字段

这部分在基础服务层已经做了日志脱敏处理。

## 异常处理

包内统一抛出：

```php
EasyHuifu\Exception\EasyHuifuException
```

你在业务项目里建议统一捕获这个异常，再决定：

- 返回给后台操作人
- 写业务日志
- 触发短信提醒
- 做失败重试

示例：

```php
try {
    $huifu->payout()->payToActor($payload);
} catch (\EasyHuifu\Exception\EasyHuifuException $e) {
    // 这里做你自己的业务异常处理
    throw $e;
}
```

## 什么时候必须注入 `entry_repository`

以下场景必须注入：

- 调 `payout()->payToActor()` 时不直接传 `huifu_id`
- 调 `entry()->detailByActor()`
- 想在进件成功后自动保存快照档案

以下场景可以不注入：

- 打款时直接传 `huifu_id`
- 单纯做退款
- 单纯做进件但不落库存档

## 什么时候必须注入 `branch_code_resolver`

以下场景必须注入：

- 企业银行卡只传 `branch_name`
- 没有直接传 `branch_code`

以下场景可以不注入：

- 企业银行卡直接传了 `branch_code`
- 个人银行卡场景

## 推荐的复用姿势

如果你打算给别的项目用，建议按下面顺序推进：

1. 先在新项目里直接接 `easyhuifu`
2. 先落最独立的退款能力
3. 再接打款能力
4. 最后接进件和进件档案持久化

原因很简单：

- 退款耦合最浅
- 打款通常需要进件档案查询
- 进件最深，既有请求字段拼装，也有快照持久化，还可能涉及联行号解析

## 对当前仓库的迁移建议

不要立刻回头把当前项目强行整体替换成 `easyhuifu`。

更稳妥的方式是：

1. 先把 `easyhuifu` 在新项目跑通
2. 再给当前项目补适配器
3. 最后视情况把当前项目的汇付服务逐步切到这个包

如果后续你准备把当前项目接入这个包，可以参考：

- [docs/THINKPHP_ADAPTER.md](docs/THINKPHP_ADAPTER.md)

## 当前版本的边界

目前这个包主要覆盖了你当前项目已经稳定在用的几类能力：

- 余额打款
- 扫码退款
- 个人/企业进件
- 业务开通

它还不是“汇付全量 SDK 二次封装”，所以这几个边界需要明确：

- 不是所有汇付接口都已封装
- 不是所有字段校验都做成了完全通用化配置
- 默认策略目前偏向你现有项目的实际业务规则
- 如果新项目的进件字段模型差异很大，建议通过适配层整理后再调用本包

## 后续建议

如果你准备正式对外复用，下一步建议做这几件事：

1. 把 `packages/easyhuifu` 单独拆成独立 Git 仓库
2. 补单元测试，至少覆盖 `payout / refund / entry`
3. 补一个 `examples/` 目录，放原生 PHP 和 ThinkPHP 示例
4. 补版本号和变更日志
5. 确定正式发布方式：私有 Composer 仓库、GitLab Package Registry 或 Packagist

## 相关文件

- [src/Application.php](src/Application.php)
- [src/Config.php](src/Config.php)
- [src/bootstrap.php](src/bootstrap.php)
- [src/Foundation/BsPayClientFactory.php](src/Foundation/BsPayClientFactory.php)
- [src/Service/PayoutService.php](src/Service/PayoutService.php)
- [src/Service/RefundService.php](src/Service/RefundService.php)
- [src/Service/EntryService.php](src/Service/EntryService.php)
- [docs/THINKPHP_ADAPTER.md](docs/THINKPHP_ADAPTER.md)
