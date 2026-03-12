# examples

这个目录放的是可直接复制的接入示例，目标不是展示所有能力，而是解决两个最常见问题：

1. 别的项目怎么通过 Composer 引入 `easyhuifu`
2. ThinkPHP 项目怎么快速把 `easyhuifu` 跑起来
3. 怎么直接使用包内置的联行号和汇付地区编码数据

## 目录说明

- `composer-vcs-repository.json`
  适合仓库已经在 GitHub 上，但还没有提交到 Packagist 的场景
- `composer-path-repository.json`
  适合本地联调，或者同一台机器多个项目同时开发的场景
- `native-php.php`
  原生 PHP 最小初始化和调用示例，包含支付、打款、退款
- `../data/bank_branch_codes.ndjson`
  包内置联行号字典数据
- `../data/huifu_regions.json`
  包内置汇付地区编码数据
- `thinkphp/HuifuApplicationFactory.php`
  ThinkPHP 下统一创建 `EasyHuifu\Application` 的工厂示例
- `thinkphp/ThinkHuifuLogger.php`
  ThinkPHP 日志适配器示例
- `thinkphp/ThinkHuifuEntryRepository.php`
  ThinkPHP 进件档案仓储适配器示例
- `thinkphp/ThinkHuifuBranchCodeResolver.php`
  ThinkPHP 联行号解析适配器示例

## Composer 引入示例

### 1. GitHub 仓库直连

如果仓库还没有上 Packagist，可以先在目标项目 `composer.json` 里加：

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

### 2. 本地 path 仓库

如果 `easyhuifu` 仓库和你的业务项目都在本机，可以这样接：

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

然后执行：

```bash
composer update gaooooge/easyhuifu
```

## 推荐使用方式

### 新项目

优先直接接 `easyhuifu`：

- 支付直接调 `pay()->miniApp()` / `pay()->jsPay()`
- 退款直接调用
- 打款优先直接传 `huifu_id`
- 有进件档案需求时再接 `entry_repository`

### 老 ThinkPHP 项目

优先顺序：

1. 先接 `ThinkHuifuLogger`
2. 再接 `ThinkHuifuEntryRepository`
3. 如果你要覆盖包内置联行号字典，再接 `ThinkHuifuBranchCodeResolver`
4. 再通过 `HuifuApplicationFactory` 统一创建实例

## 复制建议

如果你要快速落地，建议按这个顺序复制：

1. 先复制 `composer-vcs-repository.json` 里的配置到目标项目
2. 再复制 `native-php.php` 或 `thinkphp/HuifuApplicationFactory.php`
3. 最后按需要复制对应适配器

## 使用包内置字典

### 联行号

```php
$banks = $huifu->bankBranches()->getBankOptions('建设');
$branchCode = $huifu->bankBranches()->resolveBranchCode('中国建设银行上海某某支行', '01050000');
$valid = $huifu->bankBranches()->isValidBranchCode($branchCode);
$branchList = $huifu->bankBranches()->matchBranches('张江', '01050000', 10);
```

说明：

- `isValidBranchCode()` 用来校验联行号是否存在
- `resolveBranchCode()` 用来通过支行名称取联行号
- 如果同名支行不止一条，建议同时传 `head_bank_code`，否则会返回空字符串避免误匹配

### 汇付地区编码

```php
$tree = $huifu->regions()->tree();
$provinceList = $huifu->regions()->getChildren('');
$cityList = $huifu->regions()->getChildren('310000');
$districtCode = $huifu->regions()->getCodeByName('浦东新区', 3, '310100');
```

## `miniApp` 延迟分账示例

`native-php.php` 里已经带了一个最小可用示例，核心参数就是这两个：

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

其中：

- `delay_acct_flag` 传 `Y` 表示开启延迟分账
- `acct_split_bunch` 直接按数组传即可，包内会自动转成 SDK 需要的 JSON
