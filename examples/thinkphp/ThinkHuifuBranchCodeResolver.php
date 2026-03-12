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
