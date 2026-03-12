<?php

namespace EasyHuifu\Support;

class ArrayBankBranchRepository
{
    private static $bankOptions;

    private $dataFile;
    private $bankOptionsFile;

    public function __construct($dataFile = null, $bankOptionsFile = null)
    {
        $baseDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data';
        $this->dataFile = $dataFile ?: $baseDir . DIRECTORY_SEPARATOR . 'bank_branch_codes.ndjson';
        $this->bankOptionsFile = $bankOptionsFile ?: $baseDir . DIRECTORY_SEPARATOR . 'bank_options.json';
    }

    public function getBankOptions($keyword = '', $limit = 200)
    {
        $this->boot();
        $keyword = trim((string)$keyword);
        $items = self::$bankOptions;

        if ($keyword !== '') {
            $items = array_values(array_filter($items, function ($item) use ($keyword) {
                return $this->contains($item['bank_name'], $keyword)
                    || $this->contains($item['head_bank_code'], $keyword);
            }));
        }

        return array_slice($items, 0, max(1, (int)$limit));
    }

    public function getBranchList(array $params = [])
    {
        $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
        $pageSize = isset($params['pageSize']) ? (int)$params['pageSize'] : 20;
        if ($pageSize <= 0 || $pageSize > 100) {
            $pageSize = 20;
        }

        $bankName = isset($params['bank_name']) ? trim((string)$params['bank_name']) : '';
        $headBankCode = isset($params['head_bank_code']) ? trim((string)$params['head_bank_code']) : '';
        $keyword = isset($params['keyword']) ? trim((string)$params['keyword']) : '';
        $unionCode = isset($params['union_code']) ? trim((string)$params['union_code']) : '';

        $total = 0;
        $offset = ($page - 1) * $pageSize;
        $items = [];

        $this->scanRows(function ($item) use ($bankName, $headBankCode, $keyword, $unionCode, $offset, $pageSize, &$total, &$items) {
            if (!$this->matches($item, $bankName, $headBankCode, $keyword, $unionCode)) {
                return;
            }

            if ($total >= $offset && count($items) < $pageSize) {
                $items[] = $item;
            }
            $total++;
        });

        return [
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'list' => $items,
        ];
    }

    public function getByUnionCode($unionCode)
    {
        $unionCode = trim((string)$unionCode);
        if ($unionCode === '') {
            return null;
        }

        $matched = null;
        $this->scanRows(function ($item) use ($unionCode, &$matched) {
            if ($matched !== null) {
                return;
            }
            if ($item['union_code'] === $unionCode) {
                $matched = $item;
            }
        });

        return $matched;
    }

    public function isValidBranchCode($unionCode)
    {
        return $this->getByUnionCode($unionCode) !== null;
    }

    public function resolveBranchCode($branchName, $bankCode = '')
    {
        $branchName = trim((string)$branchName);
        $bankCode = trim((string)$bankCode);
        if ($branchName === '') {
            return '';
        }

        $result = [];
        foreach ($this->getBranchList([
            'head_bank_code' => $bankCode,
            'keyword' => $branchName,
            'page' => 1,
            'pageSize' => 20,
        ])['list'] as $item) {
            if ($item['branch_name'] === $branchName) {
                $result[] = $item;
            }
        }

        if (empty($result)) {
            return '';
        }
        if (count($result) > 1 && $bankCode === '') {
            return '';
        }

        return (string)$result[0]['union_code'];
    }

    public function matchBranches($keyword, $bankCode = '', $limit = 20)
    {
        $result = $this->getBranchList([
            'head_bank_code' => $bankCode,
            'keyword' => $keyword,
            'page' => 1,
            'pageSize' => max(1, (int)$limit),
        ]);

        return $result['list'];
    }

    private function boot()
    {
        if (self::$bankOptions !== null) {
            return;
        }

        self::$bankOptions = json_decode(file_get_contents($this->bankOptionsFile), true);
        if (!is_array(self::$bankOptions)) {
            self::$bankOptions = [];
        }
        usort(self::$bankOptions, function ($a, $b) {
            return strcmp($a['bank_name'], $b['bank_name']);
        });
    }

    private function scanRows(callable $callback)
    {
        $fp = fopen($this->dataFile, 'rb');
        if ($fp === false) {
            return;
        }

        while (($line = fgets($fp)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $item = json_decode($line, true);
            if (!is_array($item)) {
                continue;
            }

            $callback($item);
        }

        fclose($fp);
    }

    private function matches(array $item, $bankName, $headBankCode, $keyword, $unionCode)
    {
        if ($bankName !== '' && $item['bank_name'] !== $bankName) {
            return false;
        }
        if ($headBankCode !== '' && $item['head_bank_code'] !== $headBankCode) {
            return false;
        }
        if ($unionCode !== '' && $item['union_code'] !== $unionCode) {
            return false;
        }
        if ($keyword !== '') {
            $matched = $this->contains($item['branch_name'], $keyword)
                || $this->contains($item['union_code'], $keyword)
                || $this->contains($item['bank_name'], $keyword);
            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    private function contains($haystack, $needle)
    {
        $haystack = (string)$haystack;
        $needle = (string)$needle;

        if (function_exists('mb_stripos')) {
            return mb_stripos($haystack, $needle) !== false;
        }

        return stripos($haystack, $needle) !== false;
    }
}
