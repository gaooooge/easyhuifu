# ThinkPHP Adapter Notes

If you later want to migrate the current ThinkPHP project to `easyhuifu`, do it via adapters.

## Recommended adapters

### Entry repository

Wrap the current `huifu_user_entry` table access behind:

- `findSuccessEntry($appId, $roleType, $actorId)`
- `findLatestEntry(array $context, $entryType = '')`
- `saveEntryArchive(array $data)`

### Logger

Wrap the current:

```php
Log::channel('huifu')->info(...)
Log::channel('huifu')->error(...)
```

behind:

- `info($event, array $context = [])`
- `error($event, array $context = [])`

### Branch code resolver

Wrap your current `bank_branch_codes` query logic behind:

- `resolve($branchName, $bankCode = '')`

## Recommended migration order

1. Refund
2. Payout
3. Entry

Refund is the most isolated. Entry is the deepest because it also touches persistence.

## Thin adapter example

```php
use EasyHuifu\Contracts\EntryRepositoryInterface;

class ThinkHuifuEntryRepository implements EntryRepositoryInterface
{
    public function findSuccessEntry($appId, $roleType, $actorId)
    {
        $model = new \app\common\model\huifu\UserEntry();

        if ($appId > 0) {
            $exact = $model->withoutGlobalScope()->where([
                'app_id' => $appId,
                'role_type' => $roleType,
                'actor_id' => $actorId,
                'entry_status' => 1,
            ])->find();
            if ($exact && !empty($exact['huifu_id'])) {
                return $exact->toArray();
            }
        }

        $row = $model->withoutGlobalScope()
            ->where([
                'role_type' => $roleType,
                'actor_id' => $actorId,
                'entry_status' => 1,
            ])
            ->where('huifu_id', '<>', '')
            ->order('update_time desc,id desc')
            ->find();

        return $row ? $row->toArray() : null;
    }

    public function findLatestEntry(array $context, $entryType = '')
    {
        $query = (new \app\common\model\huifu\UserEntry())
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
        \app\common\model\huifu\UserEntry::saveByActor($data);
    }
}
```

Keep the adapter thin. Do not move business rules back into ThinkPHP once they already exist inside `easyhuifu`.
