<?php

/**
 * 参考数据导出脚本。
 *
 * 说明：
 * - 这个脚本只用于维护 easyhuifu 包内置的参考数据文件
 * - 运行时不会连接任何业务项目数据库
 * - 导出联行号数据时，必须通过环境变量显式传入数据库连接信息
 */

$root = dirname(__DIR__);
$dataDir = $root . DIRECTORY_SEPARATOR . 'data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

$regionUrl = 'https://cloudpnrcdn.oss-cn-shanghai.aliyuncs.com/opps/api/prod/download_file/area/%E7%9C%81%E5%B8%82%E5%8C%BA%E7%BC%96%E7%A0%81.json';

$dbHost = requireEnv('EASYHUIFU_DB_HOST');
$dbPort = getenv('EASYHUIFU_DB_PORT') ?: '3306';
$dbName = requireEnv('EASYHUIFU_DB_NAME');
$dbUser = requireEnv('EASYHUIFU_DB_USER');
$dbPass = requireEnv('EASYHUIFU_DB_PASS');
$dbPrefix = getenv('EASYHUIFU_DB_PREFIX') ?: '';

writeRegionData($regionUrl, $dataDir . DIRECTORY_SEPARATOR . 'huifu_regions.json');
writeBranchData(
    $dbHost,
    $dbPort,
    $dbName,
    $dbUser,
    $dbPass,
    $dbPrefix . 'bank_branch_codes',
    $dataDir . DIRECTORY_SEPARATOR . 'bank_branch_codes.ndjson',
    $dataDir . DIRECTORY_SEPARATOR . 'bank_options.json'
);

echo 'reference data exported', PHP_EOL;

function requireEnv($name)
{
    $value = getenv($name);
    if ($value === false || trim((string)$value) === '') {
        throw new RuntimeException('Missing required env: ' . $name);
    }

    return trim((string)$value);
}

function writeRegionData($url, $targetFile)
{
    $ctx = stream_context_create([
        'http' => ['method' => 'GET', 'timeout' => 20],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $raw = file_get_contents($url, false, $ctx);
    if ($raw === false) {
        throw new RuntimeException('Failed to fetch remote huifu regions');
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid huifu region payload');
    }

    $normalized = normalizeHuifuRegionData($decoded);
    file_put_contents($targetFile, json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function writeBranchData($host, $port, $dbName, $user, $pass, $table, $targetFile, $bankOptionsFile)
{
    $pdo = new PDO(
        'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbName . ';charset=utf8mb4',
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->query(
        'SELECT id, bank_name, branch_name, union_code, head_bank_code FROM ' . $table . ' ORDER BY id ASC'
    );

    $fp = fopen($targetFile, 'wb');
    if ($fp === false) {
        throw new RuntimeException('Unable to open branch output file');
    }

    $banks = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $item = [
            'id' => (int)$row['id'],
            'bank_name' => (string)$row['bank_name'],
            'branch_name' => (string)$row['branch_name'],
            'union_code' => (string)$row['union_code'],
            'head_bank_code' => (string)$row['head_bank_code'],
        ];

        $bankKey = $item['bank_name'] . '|' . $item['head_bank_code'];
        if (!isset($banks[$bankKey])) {
            $banks[$bankKey] = [
                'bank_name' => $item['bank_name'],
                'head_bank_code' => $item['head_bank_code'],
            ];
        }

        fwrite($fp, json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }
    fclose($fp);

    file_put_contents($bankOptionsFile, json_encode(array_values($banks), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function normalizeHuifuRegionData($rawData)
{
    $list = extractRegionList($rawData);

    return normalizeRegionList($list, 1);
}

function extractRegionList($data)
{
    if (!is_array($data)) {
        return [];
    }
    if (isListArray($data)) {
        return $data;
    }

    foreach (['data', 'list', 'rows', 'result'] as $key) {
        if (isset($data[$key]) && is_array($data[$key])) {
            return extractRegionList($data[$key]);
        }
    }

    return $data;
}

function normalizeRegionList($data, $level = 1)
{
    if (!is_array($data)) {
        return [];
    }

    $currentLevel = max(1, min(3, (int)$level));
    $list = [];
    if (isListArray($data)) {
        foreach ($data as $item) {
            $node = normalizeRegionNode($item, '', $currentLevel);
            if (!empty($node)) {
                $list[] = $node;
            }
        }

        return $list;
    }

    foreach ($data as $key => $item) {
        $node = normalizeRegionNode($item, (string)$key, $currentLevel);
        if (!empty($node)) {
            $list[] = $node;
        }
    }

    return $list;
}

function normalizeRegionNode($item, $fallbackKey = '', $level = 1)
{
    if (!is_array($item)) {
        $value = trim((string)$item);
        $key = trim((string)$fallbackKey);
        $keyIsCode = isLikelyRegionCode($key);
        $valueIsCode = isLikelyRegionCode($value);

        if ($key !== '' && !$keyIsCode && $valueIsCode) {
            $name = $key;
            $code = $value;
        } elseif ($key !== '' && $keyIsCode && !$valueIsCode) {
            $name = $value;
            $code = $key;
        } else {
            $name = $key !== '' ? $key : $value;
            $code = $value !== '' ? $value : $key;
        }

        return [
            'name' => trim((string)$name),
            'code' => (string)$code,
            'level' => (int)$level,
            'children' => [],
        ];
    }

    $name = pickString($item, ['name', 'region_name', 'regionName', 'label', 'text', 'title', 'full_name']);
    $code = pickString($item, ['code', 'val', 'region_code', 'regionCode', 'value', 'id', 'area_id', 'areaCode']);

    if ($name === '' && !is_numeric($fallbackKey)) {
        $name = $fallbackKey;
    }
    if ($code === '' && $fallbackKey !== '') {
        $code = $fallbackKey;
    }

    $childrenRaw = [];
    foreach ([
        'children', 'child', 'list', 'items',
        'city', 'citys', 'city_list', 'cityList',
        'area', 'areas', 'area_list', 'areaList',
        'district', 'districts', 'district_list', 'districtList',
        'county', 'counties', 'county_list', 'countyList',
        'next',
    ] as $field) {
        if (isset($item[$field]) && is_array($item[$field])) {
            $childrenRaw = $item[$field];
            break;
        }
    }

    $children = normalizeRegionList($childrenRaw, min(3, $level + 1));

    if ($name === '') {
        if ($fallbackKey !== '' && is_numeric($fallbackKey)) {
            $name = pickFirstScalarString($item);
            $code = $code !== '' ? $code : $fallbackKey;
        } else {
            return [];
        }
    }

    return [
        'name' => trim((string)$name),
        'code' => (string)$code,
        'level' => (int)$level,
        'children' => $children,
    ];
}

function pickString(array $data, array $keys)
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $data)) {
            continue;
        }
        $value = $data[$key];
        if ($value === null || $value === '') {
            continue;
        }
        if (is_scalar($value)) {
            return trim((string)$value);
        }
    }

    return '';
}

function pickFirstScalarString(array $data)
{
    foreach ($data as $value) {
        if (is_scalar($value) && $value !== '') {
            return trim((string)$value);
        }
    }

    return '';
}

function isListArray(array $data)
{
    if (function_exists('array_is_list')) {
        return array_is_list($data);
    }

    $i = 0;
    foreach ($data as $key => $value) {
        if ($key !== $i++) {
            return false;
        }
    }

    return true;
}

function isLikelyRegionCode($value)
{
    return preg_match('/^\d{6,12}$/', trim((string)$value)) === 1;
}
