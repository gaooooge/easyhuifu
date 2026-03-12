# ThinkPHP 接入说明

本文档说明如何在 ThinkPHP 应用中接入 `easyhuifu`，并通过适配器对接日志、进件档案及联行号解析能力。

## 设计原则

- `easyhuifu` 保持框架无关
- ThinkPHP 侧仅提供外部依赖的适配实现
- 业务规则尽量保留在调用方
- 数据库存取、日志输出、字典查询通过接口注入

## 适配范围

以下场景通常需要适配器：

- 打款时需要根据主体信息查询进件档案
- 进件成功后需要持久化归档
- 进件回显需要查询最近一条记录
- 需要复用 ThinkPHP 的日志输出能力
- 需要使用自定义联行号解析逻辑

以下场景通常不强制要求适配器：

- 直接传 `huifu_id` 打款
- 仅使用退款能力
- 企业银行卡直接传 `branch_code`

## 推荐结构

建议将适配器放在独立目录，例如：

```text
app/
  common/
    adapter/
      huifu/
        ThinkHuifuEntryRepository.php
        ThinkHuifuLogger.php
        ThinkHuifuBranchCodeResolver.php
```

## 进件仓储适配器

对应接口：

```php
EasyHuifu\Contracts\EntryRepositoryInterface
```

需要实现的方法：

```php
findSuccessEntry($appId, $roleType, $actorId)
findLatestEntry(array $context, $entryType = '')
saveEntryArchive(array $data)
```

### 示例

```php
<?php

namespace app\common\adapter\huifu;

use EasyHuifu\Contracts\EntryRepositoryInterface;
use app\common\model\huifu\UserEntry;

class ThinkHuifuEntryRepository implements EntryRepositoryInterface
{
    public function findSuccessEntry($appId, $roleType, $actorId)
    {
        $model = new UserEntry();

        if ((int)$appId > 0) {
            $exact = $model->withoutGlobalScope()->where([
                'app_id' => (int)$appId,
                'role_type' => (string)$roleType,
                'actor_id' => (int)$actorId,
                'entry_status' => 1,
            ])->find();

            if ($exact && !empty($exact['huifu_id'])) {
                return $exact->toArray();
            }
        }

        $row = $model->withoutGlobalScope()
            ->where([
                'role_type' => (string)$roleType,
                'actor_id' => (int)$actorId,
                'entry_status' => 1,
            ])
            ->where('huifu_id', '<>', '')
            ->order('update_time desc,id desc')
            ->find();

        return $row ? $row->toArray() : null;
    }

    public function findLatestEntry(array $context, $entryType = '')
    {
        $query = (new UserEntry())
            ->withoutGlobalScope()
            ->where([
                'role_type' => (string)$context['role_type'],
                'actor_id' => (int)$context['actor_id'],
            ]);

        if (!empty($context['app_id'])) {
            $query->where('app_id', (int)$context['app_id']);
        }

        if ($entryType !== '') {
            $query->where('entry_type', (string)$entryType);
        }

        $row = $query->order('update_time desc,id desc')->find();

        return $row ? $row->toArray() : null;
    }

    public function saveEntryArchive(array $data)
    {
        UserEntry::saveByActor($data);
    }
}
```

## 日志适配器

对应接口：

```php
EasyHuifu\Contracts\LoggerInterface
```

需要实现的方法：

```php
info($event, array $context = [])
error($event, array $context = [])
```

### 示例

```php
<?php

namespace app\common\adapter\huifu;

use EasyHuifu\Contracts\LoggerInterface;
use think\facade\Log;

class ThinkHuifuLogger implements LoggerInterface
{
    public function info($event, array $context = [])
    {
        Log::channel('huifu')->info(
            $event . ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public function error($event, array $context = [])
    {
        Log::channel('huifu')->error(
            $event . ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
```

## 联行号解析适配器

对应接口：

```php
EasyHuifu\Contracts\BranchCodeResolverInterface
```

需要实现的方法：

```php
resolve($branchName, $bankCode = '')
```

### 示例

```php
<?php

namespace app\common\adapter\huifu;

use EasyHuifu\Contracts\BranchCodeResolverInterface;
use app\common\model\settings\BankBranchCode;

class ThinkHuifuBranchCodeResolver implements BranchCodeResolverInterface
{
    public function resolve($branchName, $bankCode = '')
    {
        $branchName = trim((string)$branchName);
        $bankCode = trim((string)$bankCode);
        if ($branchName === '') {
            return '';
        }

        $query = (new BankBranchCode())->where('branch_name', $branchName);
        if ($bankCode !== '') {
            $query->where('head_bank_code', $bankCode);
        }

        $list = $query->select();
        if (!$list || count($list) !== 1) {
            return '';
        }

        return (string)$list[0]['union_code'];
    }
}
```

说明：

- 若本地字典已满足要求，可不注入该适配器
- 仅在需要覆盖默认解析策略时建议实现

## Application 组装

```php
<?php

namespace app\common\adapter\huifu;

use EasyHuifu\Application;

class HuifuApplicationFactory
{
    public static function make(array $config)
    {
        return new Application($config, [
            'logger' => new ThinkHuifuLogger(),
            'entry_repository' => new ThinkHuifuEntryRepository(),
            'branch_code_resolver' => new ThinkHuifuBranchCodeResolver(),
        ]);
    }
}
```

## 调用示例

### 打款

```php
$huifu = HuifuApplicationFactory::make($config);

$result = $huifu->payout()->payToActor([
    'role_type' => 'supplier',
    'actor_id' => 20001,
    'app_id' => 10000,
    'amount' => 88.00,
    'remark' => '供应商提现打款',
]);
```

### 退款

```php
$result = $huifu->refund()->scanPay([
    'huifu_req_seq_id' => 'rQ202603120001',
    'transaction_id' => '0036000123456789',
    'pay_time' => time(),
], 10.00, 'refund202603120001');
```

### 支付

```php
$result = $huifu->pay()->miniApp([
    'amount' => 0.01,
    'goods_desc' => '订单支付',
    'order_no' => 'M202603120001',
    'notify_url' => 'https://your-domain.com/payment/huifu/notify',
    'sub_appid' => 'wx1234567890abcdef',
    'sub_openid' => 'oUpF8uMuAJO_M2pxb1Q9zNjWeS6o',
]);
```

## 接入建议

- 优先完成日志适配
- 再实现进件档案仓储
- 如无特殊要求，联行号优先使用默认字典能力
- 适配器仅负责桥接，不建议在适配器内重复实现服务层逻辑

## 常见问题

### 1. 是否必须实现全部适配器

不是。仅在对应能力需要外部依赖时才需要注入。

### 2. 是否必须使用本地联行号表

不是。默认已提供本地联行号字典仓储和解析逻辑。

### 3. 是否必须将日志接入 ThinkPHP

不是。未注入时默认使用空日志实现。
