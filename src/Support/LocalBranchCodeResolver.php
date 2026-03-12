<?php

namespace EasyHuifu\Support;

use EasyHuifu\Contracts\BranchCodeResolverInterface;

class LocalBranchCodeResolver implements BranchCodeResolverInterface
{
    private $repository;

    public function __construct(ArrayBankBranchRepository $repository = null)
    {
        $this->repository = $repository ?: new ArrayBankBranchRepository();
    }

    public function resolve($branchName, $bankCode = '')
    {
        return $this->repository->resolveBranchCode($branchName, $bankCode);
    }
}
