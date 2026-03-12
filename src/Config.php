<?php

namespace EasyHuifu;

use EasyHuifu\Exception\EasyHuifuException;

class Config
{
    private $items = [];

    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public function all()
    {
        return $this->items;
    }

    public function get($key, $default = null)
    {
        return array_key_exists($key, $this->items) ? $this->items[$key] : $default;
    }

    public function getRequired($key)
    {
        $value = $this->get($key, '');
        if ($value === '' || $value === null) {
            throw new EasyHuifuException('Missing required huifu config: ' . $key);
        }
        return $value;
    }

    public function getSdkConfig()
    {
        return [
            'sys_id' => (string)$this->getRequired('sys_id'),
            'product_id' => (string)$this->getRequired('product_id'),
            'rsa_merch_private_key' => (string)$this->getRequired('rsa_private_key'),
            'rsa_huifu_public_key' => (string)$this->getRequired('rsa_public_key'),
        ];
    }

    public function getUpperHuifuId()
    {
        $value = (string)$this->get('upper_huifu_id', '');
        if ($value !== '') {
            return $value;
        }
        return (string)$this->getRequired('sys_id');
    }
}
