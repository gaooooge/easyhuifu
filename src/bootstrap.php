<?php

$easyHuifuSdkCandidates = [];

if (class_exists('Composer\\Autoload\\ClassLoader', false) && method_exists('Composer\\Autoload\\ClassLoader', 'getRegisteredLoaders')) {
    foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $vendorDir => $loader) {
        $easyHuifuSdkCandidates[] = $vendorDir . DIRECTORY_SEPARATOR . 'huifurepo' . DIRECTORY_SEPARATOR . 'dg-php-sdk' . DIRECTORY_SEPARATOR . 'BsPaySdk';
    }
}

$easyHuifuSdkCandidates[] = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'huifurepo' . DIRECTORY_SEPARATOR . 'dg-php-sdk' . DIRECTORY_SEPARATOR . 'BsPaySdk';
$easyHuifuSdkCandidates[] = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'huifurepo' . DIRECTORY_SEPARATOR . 'dg-php-sdk' . DIRECTORY_SEPARATOR . 'BsPaySdk';
$easyHuifuSdkCandidates = array_values(array_unique($easyHuifuSdkCandidates));

$easyHuifuSdkBase = '';
foreach ($easyHuifuSdkCandidates as $candidate) {
    if (is_dir($candidate)) {
        $easyHuifuSdkBase = $candidate;
        break;
    }
}

if ($easyHuifuSdkBase !== '' && !class_exists('BsPaySdk\\core\\BsPay', false)) {
    $initFile = $easyHuifuSdkBase . DIRECTORY_SEPARATOR . 'init.php';
    if (is_file($initFile)) {
        require_once $initFile;
    }

    spl_autoload_register(function ($class) use ($easyHuifuSdkBase) {
        $prefix = 'BsPaySdk\\';
        if (strpos($class, $prefix) !== 0) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
        $file = $easyHuifuSdkBase . DIRECTORY_SEPARATOR . $relative . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });
}
