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
