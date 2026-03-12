<?php

namespace EasyHuifu\Contracts;

interface EntryRepositoryInterface
{
    public function findSuccessEntry($appId, $roleType, $actorId);

    public function findLatestEntry(array $context, $entryType = '');

    public function saveEntryArchive(array $data);
}
