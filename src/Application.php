<?php

namespace EasyHuifu;

use EasyHuifu\Contracts\BranchCodeResolverInterface;
use EasyHuifu\Contracts\EntryRepositoryInterface;
use EasyHuifu\Contracts\LoggerInterface;
use EasyHuifu\Contracts\RegionRepositoryInterface;
use EasyHuifu\Foundation\BsPayClientFactory;
use EasyHuifu\Service\EntryService;
use EasyHuifu\Service\PayService;
use EasyHuifu\Service\PayoutService;
use EasyHuifu\Service\RefundService;
use EasyHuifu\Support\ArrayBankBranchRepository;
use EasyHuifu\Support\ArrayRegionRepository;
use EasyHuifu\Support\LocalBranchCodeResolver;
use EasyHuifu\Support\NullLogger;

class Application
{
    private $config;
    private $factory;
    private $entryRepository;
    private $branchCodeResolver;
    private $logger;
    private $regionRepository;
    private $bankBranchRepository;
    private $entryService;
    private $payService;
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
            : new LocalBranchCodeResolver();
        $this->regionRepository = isset($services['region_repository']) && $services['region_repository'] instanceof RegionRepositoryInterface
            ? $services['region_repository']
            : new ArrayRegionRepository();
        $this->bankBranchRepository = new ArrayBankBranchRepository();
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

    public function regions()
    {
        return $this->regionRepository;
    }

    public function bankBranches()
    {
        return $this->bankBranchRepository;
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

    public function pay()
    {
        if ($this->payService === null) {
            $this->payService = new PayService($this);
        }
        return $this->payService;
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
