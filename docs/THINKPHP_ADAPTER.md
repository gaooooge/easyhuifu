# ThinkPHP 适配说明

这份文档说明的是：

- 如果你后续想让当前 ThinkPHP 项目接入 `easyhuifu`
- 但又不想一上来就大改现有业务逻辑
- 应该怎么通过“适配器”把当前项目和 `easyhuifu` 接起来

核心原则只有一句话：

不要把 `easyhuifu` 再改回一个依赖 ThinkPHP 的项目内服务。

正确方向是：

- `easyhuifu` 继续保持独立、通用、无框架耦合
- 当前项目只负责提供一层很薄的适配器
- 适配器只做“数据来源对接”和“基础组件桥接”
- 业务规则仍然尽量保留在 `easyhuifu` 内部

## 为什么要做适配器

当前 ThinkPHP 项目已经有这些现成能力：

- 汇付进件档案表
- 汇付日志通道
- 联行号字典表
- 各种业务主体模型

但这些能力在别的项目里未必存在，也未必用的是同一套框架和表结构。

所以 `easyhuifu` 不能直接写死：

- `HuifuUserEntryModel`
- `Log::channel('huifu')`
- `BankBranchCodeModel`
- `env()`
- `request()`

这也是为什么包里只保留接口：

- `EntryRepositoryInterface`
- `LoggerInterface`
- `BranchCodeResolverInterface`

你在 ThinkPHP 项目里要做的，就是把这几个接口各自实现一遍。

## 适配的总体思路

推荐拆成三层：

1. `easyhuifu`
   独立包，负责汇付能力封装

2. ThinkPHP adapter
   负责把当前项目的数据表、日志、字典表接到包接口上

3. 当前业务服务/控制器
   继续按你现有的业务调用方式工作，后续再逐步切换到包

也就是说，适配器不是替代你的业务逻辑，而是给包提供“外部依赖”。

## 什么时候需要做适配

### 必须做适配的场景

- 打款时不直接传 `huifu_id`，而是要按 `role_type + actor_id + app_id` 查进件档案
- 进件成功后要落库存档
- 进件回显要查最近一次进件记录
- 企业银行卡只传支行名称，要自动解析联行号
- 你希望包内日志继续走当前项目的 `Log::channel('huifu')`

### 不一定要做适配的场景

- 新项目直接传 `huifu_id` 打款
- 单独做退款
- 进件后不需要入库
- 企业银行卡直接传 `branch_code`

## 推荐迁移顺序

不要一开始就试图把当前项目所有汇付逻辑一把切过去。

建议顺序：

1. 退款
2. 打款
3. 进件

原因很简单：

- 退款最独立，几乎不依赖项目内表结构
- 打款一般要依赖进件档案查询
- 进件最深，既涉及请求参数拼装，也涉及落库存档、回显、联行号解析

## 适配器清单

当前推荐至少实现三个适配器：

1. `ThinkHuifuEntryRepository`
2. `ThinkHuifuLogger`
3. `ThinkHuifuBranchCodeResolver`

## 一、进件仓储适配器

这是最重要的适配器。

它负责把当前项目里的 `huifu_user_entry` 表桥接到 `easyhuifu`。

对应接口：

```php
EasyHuifu\Contracts\EntryRepositoryInterface
```

需要实现三个方法：

```php
findSuccessEntry($appId, $roleType, $actorId)
findLatestEntry(array $context, $entryType = '')
saveEntryArchive(array $data)
```

### 1. `findSuccessEntry`

用途：

- 给打款服务用
- 根据主体信息查“可用的成功进件记录”

推荐行为：

- 如果传了 `app_id`，优先查当前应用的进件档案
- 找不到再回退到同主体的最近一条成功进件
- 必须保证返回结果里有 `huifu_id`

### 2. `findLatestEntry`

用途：

- 给进件回显用
- 查某个主体最近一条进件记录

推荐行为：

- 按 `role_type + actor_id` 查
- 如果有 `app_id`，则附加应用过滤
- 如果传了 `entryType`，则附加 `entry_type` 过滤
- 最后按 `update_time desc, id desc` 取最近一条

### 3. `saveEntryArchive`

用途：

- 给进件成功后的快照持久化用

推荐行为：

- 直接复用当前项目的 `HuifuUserEntry::saveByActor($data)`
- 不要在适配器里自己重新发明一套保存逻辑

### 示例实现

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

## 二、日志适配器

如果你想继续沿用当前项目的：

```php
Log::channel('huifu')
```

那就给 `easyhuifu` 注入一个日志适配器。

对应接口：

```php
EasyHuifu\Contracts\LoggerInterface
```

需要实现：

```php
info($event, array $context = [])
error($event, array $context = [])
```

### 推荐做法

- 保持实现尽量简单
- 只负责把包里的结构化日志转发到 ThinkPHP 日志通道
- 不要在这里混入业务判断

### 示例实现

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

## 三、联行号解析适配器

企业进件时，如果只传了：

- `branch_name`

但没有传：

- `branch_code`

那么 `easyhuifu` 会要求你自己提供一个联行号解析器。

对应接口：

```php
EasyHuifu\Contracts\BranchCodeResolverInterface
```

需要实现：

```php
resolve($branchName, $bankCode = '')
```

### 推荐行为

- 按支行名称查字典表
- 如果有 `bankCode`，先加银行总行编码过滤
- 查不到就返回空字符串或抛异常都可以，但建议返回空字符串让包统一抛异常
- 如果重名过多，建议你自己在适配器里收紧条件

### 示例实现

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

        $query = (new BankBranchCode())
            ->withoutGlobalScope()
            ->where('branch_name', $branchName);

        if ($bankCode !== '') {
            $query->where('head_bank_code', $bankCode);
        }

        $row = $query->order('id asc')->find();

        return $row ? (string)$row['union_code'] : '';
    }
}
```

## 在 ThinkPHP 项目里怎么组装

把这些适配器准备好后，就可以在项目里组一个 `Application` 实例。

示例：

```php
<?php

use EasyHuifu\Application;
use app\common\adapter\huifu\ThinkHuifuLogger;
use app\common\adapter\huifu\ThinkHuifuEntryRepository;
use app\common\adapter\huifu\ThinkHuifuBranchCodeResolver;

$huifu = new Application([
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
```

## 推荐放在哪里

如果你准备在当前 ThinkPHP 项目里接入，建议把适配器统一放在类似目录：

```php
app/common/adapter/huifu/
```

例如：

- `app/common/adapter/huifu/ThinkHuifuLogger.php`
- `app/common/adapter/huifu/ThinkHuifuEntryRepository.php`
- `app/common/adapter/huifu/ThinkHuifuBranchCodeResolver.php`

不要把这些适配器继续塞回 `app/common/service/huifu/` 里。

原因很简单：

- `service/huifu` 更像项目内业务服务
- `adapter/huifu` 更符合“给外部包做桥接”的职责

## 怎么在当前项目里渐进迁移

### 方案一：先新项目使用，当前项目不动

这是最稳的方案。

做法：

1. `easyhuifu` 先只给新项目使用
2. 当前项目继续使用原有服务
3. 等新项目验证稳定后，再决定是否回迁当前项目

这个方案风险最低。

### 方案二：从退款开始切

如果你要在当前项目里试点迁移，优先从退款切。

原因：

- 退款不依赖进件档案仓储
- 不依赖联行号解析
- 改动最小

推荐方式：

- 原有 `TradeRefundService` 保留
- 新增一个基于 `easyhuifu` 的薄包装服务
- 先在一处业务里试点调用

### 方案三：再切打款

打款比退款深一点，因为通常依赖进件档案表。

前提：

- 你已经实现好 `ThinkHuifuEntryRepository`

然后可以在新服务里这样调：

```php
$huifu->payout()->payToActor([
    'role_type' => 'supplier',
    'actor_id' => 20001,
    'app_id' => 10000,
    'amount' => 88.00,
    'remark' => '供应商提现打款',
]);
```

### 方案四：最后再切进件

进件建议放最后。

因为它涉及：

- 参数归一化
- 银行卡信息组装
- 联行号解析
- 结算配置
- 取现配置
- 快照入库
- 回显查询

这块是最容易“看似能跑，实际埋坑”的地方。

## 适配时的常见错误

### 1. 把业务规则写回适配器

这是最常见的错误。

例如：

- 在 `EntryRepository` 里自己拼装进件参数
- 在 `Logger` 里做业务判断
- 在 `BranchCodeResolver` 里塞一堆主体类型逻辑

这些都不对。

适配器应该只做桥接，不做业务编排。

### 2. 适配器里重新实现一遍包内逻辑

比如包里已经做了：

- `settle_cycle = T1`
- `cash_type = T1`
- 平铺字段和 `card_info` 自动归一化

那你就不应该再在 ThinkPHP 适配器里手动重复做一遍。

### 3. 让包依赖 ThinkPHP Request

`easyhuifu` 不应该调用：

- `request()`
- `session()`
- `config()`

这些都应该由业务项目在外层准备好数据再传进去。

### 4. 适配器返回 ORM 对象但字段不稳定

如果你的 ORM 对象 `toArray()` 后字段不稳定，建议适配器里直接整理成普通数组返回。

包最喜欢的输入输出形式是：

- 简单数组
- 清晰字段
- 可预期结构

## 推荐的落地目录结构

如果后续要在当前项目真正接入，建议组织成这样：

```text
app/
  common/
    adapter/
      huifu/
        ThinkHuifuLogger.php
        ThinkHuifuEntryRepository.php
        ThinkHuifuBranchCodeResolver.php
    factory/
      HuifuApplicationFactory.php
    service/
      huifu/
        EasyHuifuPayoutService.php
        EasyHuifuRefundService.php
        EasyHuifuEntryService.php
```

其中：

- `adapter/huifu`
  放桥接层
- `factory/HuifuApplicationFactory.php`
  统一创建 `EasyHuifu\Application`
- `service/huifu/EasyHuifu*Service.php`
  给现有业务调用，作为过渡层

## 工厂示例

如果你不想每次都手写一遍 `new Application(...)`，推荐再包一层工厂：

```php
<?php

namespace app\common\factory;

use EasyHuifu\Application;
use app\common\adapter\huifu\ThinkHuifuLogger;
use app\common\adapter\huifu\ThinkHuifuEntryRepository;
use app\common\adapter\huifu\ThinkHuifuBranchCodeResolver;

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
```

这样你后续在任意服务里只要：

```php
$huifu = \app\common\factory\HuifuApplicationFactory::make();
```

## 最后的建议

如果你只是想让别的项目快速复用：

- 优先让新项目直接接 `easyhuifu`
- 当前项目先不要急着整体替换

如果你后续真的要让当前 ThinkPHP 项目迁过去：

- 先补适配器
- 再补工厂
- 再按“退款 -> 打款 -> 进件”的顺序替换

这样成本最低，也最不容易出回归问题。
