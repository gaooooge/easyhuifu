<?php

namespace EasyHuifu;

use EasyHuifu\Contracts\BranchCodeResolverInterface;
use EasyHuifu\Contracts\EntryRepositoryInterface;
use EasyHuifu\Contracts\LoggerInterface;
use EasyHuifu\Foundation\BsPayClientFactory;
use EasyHuifu\Service\EntryService;
use EasyHuifu\Service\PayoutService;
use EasyHuifu\Service\RefundService;
use EasyHuifu\Support\NullLogger;

class Application
{
    private $config;
    private $factory;
    private $entryRepository;
    private $branchCodeResolver;
    private $logger;
    private $entryService;
    private $payoutService;
    private $refundService;

    public function __construct(array $config, array $services = [])
    {
        $this->config = new Config($config);
        $this->logger = isset($services['logger']) && $services['logger'] instanceof LoggerInterface
            ? $services['logger']
            : new NullLogger();
        $this->entryRepository = isset($services['entry_repository']) && $services['entry_repository'] instanceof EntryRepositoryInterface
            ? $services['entry_repository']
            : null;
        $this->branchCodeResolver = isset($services['branch_code_resolver']) && $services['branch_code_resolver'] instanceof BranchCodeResolverInterface
            ? $services['branch_code_resolver']
            : null;
        $this->factory = new BsPayClientFactory($this->config, $this->logger);
    }

    public function config()
    {
        return $this->config;
    }

    public function logger()
    {
        return $this->logger;
    }

    public function entryRepository()
    {
        return $this->entryRepository;
    }

    public function branchCodeResolver()
    {
        return $this->branchCodeResolver;
    }

    public function clientFactory()
    {
        return $this->factory;
    }

    public function entry()
    {
        if ($this->entryService === null) {
            $this->entryService = new EntryService($this);
        }
        return $this->entryService;
    }

    public function payout()
    {
        if ($this->payoutService === null) {
            $this->payoutService = new PayoutService($this);
        }
        return $this->payoutService;
    }

    public function refund()
    {
        if ($this->refundService === null) {
            $this->refundService = new RefundService($this);
        }
        return $this->refundService;
    }
}
