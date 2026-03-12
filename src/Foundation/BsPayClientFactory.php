<?php

namespace EasyHuifu\Foundation;

use BsPaySdk\core\BsPay;
use BsPaySdk\core\BsPayClient;
use EasyHuifu\Config;
use EasyHuifu\Contracts\LoggerInterface;

class BsPayClientFactory
{
    private static $bootedHashes = [];

    private $config;
    private $logger;
    private $merchantKey;

    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->merchantKey = $this->buildMerchantKey();
    }

    public function create()
    {
        $this->bootSdk();

        return new BsPayClient($this->merchantKey);
    }

    public function merchantKey()
    {
        return $this->merchantKey;
    }

    private function buildMerchantKey()
    {
        $configuredKey = trim((string)$this->config->get('merchant_key', ''));
        if ($configuredKey !== '') {
            return $configuredKey;
        }

        return 'easyhuifu_' . md5(json_encode($this->config->getSdkConfig()));
    }

    private function bootSdk()
    {
        $sdkConfig = $this->config->getSdkConfig();
        $isProdMode = (bool)$this->config->get('prod_mode', true);
        $hash = md5(json_encode([
            'merchant_key' => $this->merchantKey,
            'is_prod_mode' => $isProdMode,
            'sdk_config' => $sdkConfig,
        ]));

        if (isset(self::$bootedHashes[$this->merchantKey]) && self::$bootedHashes[$this->merchantKey] === $hash) {
            BsPay::$isProdMode = $isProdMode;
            return;
        }

        BsPay::init($sdkConfig, true, $this->merchantKey);
        BsPay::$isProdMode = $isProdMode;
        self::$bootedHashes[$this->merchantKey] = $hash;

        $this->logger->info('sdk.booted', [
            'merchant_key' => $this->merchantKey,
            'is_prod_mode' => $isProdMode,
            'sys_id' => isset($sdkConfig['sys_id']) ? (string)$sdkConfig['sys_id'] : '',
        ]);
    }
}
