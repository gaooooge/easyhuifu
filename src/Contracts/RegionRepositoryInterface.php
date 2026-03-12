<?php

namespace EasyHuifu\Contracts;

interface RegionRepositoryInterface
{
    public function all();

    public function tree();

    public function detail($code);

    public function getNameByCode($code);

    public function getCodeByName($name, $level = 0, $parentCode = '');

    public function getChildren($parentCode = '');

    public function getRegionForApi();

    public function isValidCode($code);
}
