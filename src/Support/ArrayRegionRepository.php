<?php

namespace EasyHuifu\Support;

use EasyHuifu\Contracts\RegionRepositoryInterface;

class ArrayRegionRepository implements RegionRepositoryInterface
{
    private static $all;
    private static $tree;
    private static $childrenMap;

    private $dataFile;

    public function __construct($dataFile = null)
    {
        $this->dataFile = $dataFile ?: dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'huifu_regions.json';
    }

    public function all()
    {
        $this->boot();

        return self::$all;
    }

    public function tree()
    {
        $this->boot();

        return self::$tree;
    }

    public function detail($code)
    {
        $all = $this->all();
        $code = trim((string)$code);

        return $code !== '' && isset($all[$code]) ? $all[$code] : null;
    }

    public function getNameByCode($code)
    {
        $detail = $this->detail($code);

        return $detail ? $detail['name'] : '';
    }

    public function getCodeByName($name, $level = 0, $parentCode = '')
    {
        $name = trim((string)$name);
        $parentCode = trim((string)$parentCode);

        foreach ($this->all() as $item) {
            if ($item['name'] !== $name) {
                continue;
            }
            if ((int)$level > 0 && (int)$item['level'] !== (int)$level) {
                continue;
            }
            if ($parentCode !== '' && (string)$item['parent_code'] !== $parentCode) {
                continue;
            }

            return (string)$item['code'];
        }

        return '';
    }

    public function getChildren($parentCode = '')
    {
        $this->boot();
        $parentCode = trim((string)$parentCode);

        return isset(self::$childrenMap[$parentCode]) ? self::$childrenMap[$parentCode] : [];
    }

    public function getProvinceOptions()
    {
        return $this->getChildren('');
    }

    public function getCityOptions($provinceCode)
    {
        return $this->getChildren($provinceCode);
    }

    public function getDistrictOptions($cityCode)
    {
        return $this->getChildren($cityCode);
    }

    public function getRegionForApi()
    {
        return $this->tree();
    }

    public function isValidCode($code)
    {
        return $this->detail($code) !== null;
    }

    private function boot()
    {
        if (self::$all !== null) {
            return;
        }

        self::$tree = json_decode(file_get_contents($this->dataFile), true);
        if (!is_array(self::$tree)) {
            self::$tree = [];
        }
        self::$all = [];
        self::$childrenMap = [];
        $this->flatten(self::$tree, '');
    }

    private function flatten(array $nodes, $parentCode)
    {
        $parentCode = (string)$parentCode;
        self::$childrenMap[$parentCode] = [];

        foreach ($nodes as $node) {
            $current = [
                'name' => (string)$node['name'],
                'code' => (string)$node['code'],
                'level' => (int)$node['level'],
                'parent_code' => $parentCode,
                'children' => isset($node['children']) && is_array($node['children']) ? $node['children'] : [],
            ];
            self::$all[$current['code']] = $current;
            self::$childrenMap[$parentCode][] = $current;

            if (!empty($current['children'])) {
                $this->flatten($current['children'], $current['code']);
            }
        }
    }
}
