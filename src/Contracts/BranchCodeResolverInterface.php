<?php

namespace EasyHuifu\Contracts;

interface BranchCodeResolverInterface
{
    public function resolve($branchName, $bankCode = '');
}
