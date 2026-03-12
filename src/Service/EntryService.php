<?php

namespace EasyHuifu\Service;

use BsPaySdk\request\V2UserBasicdataEntRequest;
use BsPaySdk\request\V2UserBasicdataIndvRequest;
use BsPaySdk\request\V2UserBusiOpenRequest;
use EasyHuifu\Exception\EasyHuifuException;

class EntryService extends BaseService
{
    public function openIndividual(array $payload, array $context = [])
    {
        $context['entry_type'] = 'indv';
        $payload = $this->filterPayloadByEntryType($payload, 'indv');
        $this->assertRequired($payload, ['name', 'cert_no', 'mobile_no'], 'Individual onboarding');

        $request = new V2UserBasicdataIndvRequest();
        $request->setReqSeqId($this->buildReqSeqId('indv', $context));
        $request->setReqDate(date('Ymd'));
        $request->setName((string)$payload['name']);
        $request->setCertType((string)($payload['cert_type'] ?? '00'));
        $request->setCertNo((string)$payload['cert_no']);
        $request->setCertValidityType((string)($payload['cert_validity_type'] ?? '1'));
        $request->setCertBeginDate((string)($payload['cert_begin_date'] ?? date('Ymd')));
        $request->setMobileNo((string)$payload['mobile_no']);

        if (!empty($payload['cert_nationality'])) {
            $request->setCertNationality((string)$payload['cert_nationality']);
        }
        if (!empty($payload['address'])) {
            $request->setAddress((string)$payload['address']);
        }

        $extend = $this->buildExtend($payload, [
            'cert_end_date', 'email', 'login_name', 'sms_send_flag', 'expand_id',
            'file_list', 'mcc', 'prov_id', 'area_id', 'district_id',
        ]);
        if (!empty($extend)) {
            $request->setExtendInfo($extend);
        }

        $basicOpen = $this->request($request, 'Huifu individual basic open');
        $huifuId = $this->extractHuifuId($basicOpen);
        if ($huifuId === '') {
            throw new EasyHuifuException('Huifu individual onboarding succeeded without returning huifu_id');
        }

        $busiOpen = $this->openBusiness($huifuId, $payload, $context);
        $this->persistEntryArchive('indv', $huifuId, $payload, $context, $basicOpen, $busiOpen);

        return ['huifu_id' => $huifuId, 'basic_open' => $basicOpen, 'busi_open' => $busiOpen];
    }

    public function openEnterprise(array $payload, array $context = [])
    {
        $context['entry_type'] = 'ent';
        $payload = $this->filterPayloadByEntryType($payload, 'ent');
        $this->assertRequired($payload, [
            'reg_name', 'license_code', 'reg_prov_id', 'reg_area_id', 'reg_district_id',
            'reg_detail', 'legal_name', 'legal_cert_no', 'contact_name', 'contact_mobile',
        ], 'Enterprise onboarding');

        $licenseValidityType = (string)($payload['license_validity_type'] ?? '1');
        $licenseBeginDate = (string)($payload['license_begin_date'] ?? date('Ymd', strtotime('-1 day')));
        $legalCertValidityType = (string)($payload['legal_cert_validity_type'] ?? '1');
        $legalCertBeginDate = (string)($payload['legal_cert_begin_date'] ?? date('Ymd', strtotime('-1 day')));
        if ($licenseValidityType === '0' && empty($payload['license_end_date'])) {
            throw new EasyHuifuException('Enterprise onboarding missing required field: license_end_date');
        }
        if ($legalCertValidityType === '0' && empty($payload['legal_cert_end_date'])) {
            throw new EasyHuifuException('Enterprise onboarding missing required field: legal_cert_end_date');
        }

        $request = new V2UserBasicdataEntRequest();
        $request->setReqSeqId($this->buildReqSeqId('ent', $context));
        $request->setReqDate(date('Ymd'));
        $request->setRegName((string)$payload['reg_name']);
        $request->setLicenseCode((string)$payload['license_code']);
        $request->setLicenseValidityType($licenseValidityType);
        $request->setLicenseBeginDate($licenseBeginDate);
        $request->setRegProvId((string)$payload['reg_prov_id']);
        $request->setRegAreaId((string)$payload['reg_area_id']);
        $request->setRegDistrictId((string)$payload['reg_district_id']);
        $request->setRegDetail((string)$payload['reg_detail']);
        $request->setLegalName((string)$payload['legal_name']);
        $request->setLegalCertType((string)($payload['legal_cert_type'] ?? '00'));
        $request->setLegalCertNo((string)$payload['legal_cert_no']);
        $request->setLegalCertValidityType($legalCertValidityType);
        $request->setLegalCertBeginDate($legalCertBeginDate);
        $request->setContactName((string)$payload['contact_name']);
        $request->setContactMobile((string)$payload['contact_mobile']);
        $request->setLoginName((string)($payload['login_name'] ?? $this->buildLoginName($context)));

        if (!empty($payload['license_end_date'])) {
            $request->setLicenseEndDate((string)$payload['license_end_date']);
        }
        if (!empty($payload['legal_cert_end_date'])) {
            $request->setLegalCertEndDate((string)$payload['legal_cert_end_date']);
        }
        if (!empty($payload['legal_cert_nationality'])) {
            $request->setLegalCertNationality((string)$payload['legal_cert_nationality']);
        }

        $extend = $this->buildExtend($payload, [
            'short_name', 'contact_email', 'operator_id', 'sms_send_flag',
            'expand_id', 'file_list', 'ent_type', 'mcc',
        ]);
        if (!empty($extend)) {
            $request->setExtendInfo($extend);
        }

        $basicOpen = $this->request($request, 'Huifu enterprise basic open');
        $huifuId = $this->extractHuifuId($basicOpen);
        if ($huifuId === '') {
            throw new EasyHuifuException('Huifu enterprise onboarding succeeded without returning huifu_id');
        }

        $busiOpen = $this->openBusiness($huifuId, $payload, $context);
        $this->persistEntryArchive('ent', $huifuId, $payload, $context, $basicOpen, $busiOpen);

        return ['huifu_id' => $huifuId, 'basic_open' => $basicOpen, 'busi_open' => $busiOpen];
    }

    public function openBusiness($huifuId, array $payload = [], array $context = [])
    {
        $huifuId = trim((string)$huifuId);
        if ($huifuId === '') {
            throw new EasyHuifuException('Huifu business open failed: huifu_id is required');
        }

        $entryType = $this->resolveEntryType($payload, $context);
        $payload = $this->filterPayloadByEntryType($payload, $entryType);

        $request = new V2UserBusiOpenRequest();
        $request->setHuifuId($huifuId);
        $request->setReqSeqId($this->buildReqSeqId('busi', $context));
        $request->setReqDate(date('Ymd'));
        $request->setUpperHuifuId(!empty($payload['upper_huifu_id']) ? (string)$payload['upper_huifu_id'] : $this->config()->getUpperHuifuId());

        $ljhData = isset($payload['ljh_data']) ? $payload['ljh_data'] : $this->config()->get('ljh_data', '');
        $hxyData = isset($payload['hxy_data']) ? $payload['hxy_data'] : $this->config()->get('hxy_data', '');
        if (!empty($ljhData)) {
            $request->setLjhData($this->normalizeJson($ljhData));
        }
        if (!empty($hxyData)) {
            $request->setHxyData($this->normalizeJson($hxyData));
        }

        $extend = $this->buildExtend($payload, [
            'file_list', 'delay_flag', 'elec_acct_config',
            'open_tax_flag', 'async_return_url', 'lg_platform_type',
        ]);
        $extend['settle_config'] = $this->normalizeJson($this->buildSettleConfig($payload));
        $extend['card_info'] = $this->normalizeJson($this->buildCardInfo($payload, $entryType));
        $extend['cash_config'] = $this->normalizeJson($this->buildCashConfig($payload));
        $request->setExtendInfo($extend);

        return $this->request($request, 'Huifu business open');
    }

    public function detailByActor(array $context = [], $entryType = '')
    {
        $repository = $this->entryRepository();
        if ($repository === null) {
            throw new EasyHuifuException('Huifu entry detail requires an entry repository');
        }

        $roleType = isset($context['role_type']) ? trim((string)$context['role_type']) : '';
        $actorId = isset($context['actor_id']) ? (int)$context['actor_id'] : 0;
        if ($roleType === '' || $actorId <= 0) {
            throw new EasyHuifuException('Huifu entry detail failed: invalid role_type/actor_id');
        }

        $entry = $this->normalizeRecord($repository->findLatestEntry($context, (string)$entryType));
        if (!$entry) {
            return ['has_entry' => false, 'entry' => null];
        }

        $cardInfo = json_decode((string)($entry['card_info_json'] ?? ''), true);
        $settleConfig = json_decode((string)($entry['settle_config_json'] ?? ''), true);
        $cashConfig = json_decode((string)($entry['cash_config_json'] ?? ''), true);
        if (!is_array($cardInfo)) {
            $cardInfo = [];
        }
        if (!is_array($settleConfig)) {
            $settleConfig = [];
        }
        if (!is_array($cashConfig)) {
            $cashConfig = [];
        }

        return [
            'has_entry' => true,
            'entry' => [
                'app_id' => isset($entry['app_id']) ? (int)$entry['app_id'] : 0,
                'role_type' => isset($entry['role_type']) ? (string)$entry['role_type'] : '',
                'actor_id' => isset($entry['actor_id']) ? (int)$entry['actor_id'] : 0,
                'entry_type' => isset($entry['entry_type']) ? (string)$entry['entry_type'] : '',
                'entry_status' => isset($entry['entry_status']) ? (int)$entry['entry_status'] : 0,
                'entry_time' => isset($entry['entry_time']) ? (int)$entry['entry_time'] : 0,
                'huifu_id' => isset($entry['huifu_id']) ? (string)$entry['huifu_id'] : '',
                'card_info' => $cardInfo,
                'settle_config' => $settleConfig,
                'cash_config' => $cashConfig,
                'raw' => $entry,
            ],
        ];
    }

    private function persistEntryArchive($entryType, $huifuId, array $payload, array $context, array $basicOpen, array $busiOpen)
    {
        $repository = $this->entryRepository();
        if ($repository === null) {
            return;
        }

        $cardInfo = $this->buildCardInfo($payload, $entryType);
        $cardInfo['entry_form'] = $this->buildEntryFormSnapshot($entryType, $payload);
        $repository->saveEntryArchive([
            'app_id' => isset($context['app_id']) ? (int)$context['app_id'] : 0,
            'role_type' => isset($context['role_type']) ? trim((string)$context['role_type']) : 'user',
            'actor_id' => isset($context['actor_id']) ? (int)$context['actor_id'] : 0,
            'entry_type' => $entryType,
            'huifu_id' => (string)$huifuId,
            'entry_status' => 1,
            'entry_time' => time(),
            'card_type' => (string)($cardInfo['card_type'] ?? ''),
            'card_name' => (string)($cardInfo['card_name'] ?? ''),
            'card_no' => (string)($cardInfo['card_no'] ?? ''),
            'bank_code' => (string)($cardInfo['bank_code'] ?? ''),
            'branch_code' => (string)($cardInfo['branch_code'] ?? ''),
            'branch_name' => (string)($cardInfo['branch_name'] ?? ''),
            'prov_id' => (string)($cardInfo['prov_id'] ?? ''),
            'area_id' => (string)($cardInfo['area_id'] ?? ''),
            'cert_type' => (string)($cardInfo['cert_type'] ?? ''),
            'cert_no' => (string)($cardInfo['cert_no'] ?? ''),
            'mobile_no' => (string)($cardInfo['mp'] ?? ''),
            'card_info_json' => $this->normalizeJson($cardInfo),
            'settle_config_json' => $this->normalizeJson($this->buildSettleConfig($payload)),
            'cash_config_json' => $this->normalizeJson($this->buildCashConfig($payload)),
            'basic_open_rsp_json' => $this->normalizeJson($basicOpen),
            'busi_open_rsp_json' => $this->normalizeJson($busiOpen),
        ]);
    }

    private function buildSettleConfig(array $payload)
    {
        $settle = isset($payload['settle_config']) && is_array($payload['settle_config']) ? $payload['settle_config'] : [];
        $settle['settle_cycle'] = 'T1';
        return $settle;
    }

    private function buildCardInfo(array $payload, $entryType = '')
    {
        $card = isset($payload['card_info']) && is_array($payload['card_info']) ? $payload['card_info'] : [];
        foreach (['card_type', 'card_name', 'card_no', 'prov_id', 'area_id', 'branch_code', 'cert_type', 'cert_no', 'cert_validity_type', 'cert_begin_date', 'cert_end_date', 'mp', 'bank_code', 'branch_name'] as $field) {
            if (!isset($card[$field]) && isset($payload[$field]) && $payload[$field] !== '') {
                $card[$field] = $payload[$field];
            }
        }

        $isIndividual = $this->isIndividualPayload($payload, $entryType);
        $card['card_type'] = $isIndividual ? '1' : '0';
        $fallbackMap = [
            'card_name' => ['name', 'reg_name'],
            'cert_type' => ['cert_type', 'legal_cert_type'],
            'cert_no' => ['cert_no', 'legal_cert_no'],
            'cert_validity_type' => ['cert_validity_type', 'legal_cert_validity_type'],
            'cert_begin_date' => ['cert_begin_date', 'legal_cert_begin_date'],
            'cert_end_date' => ['cert_end_date', 'legal_cert_end_date'],
            'mp' => ['mobile_no', 'contact_mobile'],
            'prov_id' => ['prov_id', 'reg_prov_id'],
            'area_id' => ['area_id', 'reg_area_id'],
        ];
        foreach ($fallbackMap as $cardField => $payloadFields) {
            if (isset($card[$cardField]) && $card[$cardField] !== '') {
                continue;
            }
            foreach ($payloadFields as $field) {
                if (isset($payload[$field]) && $payload[$field] !== '') {
                    $card[$cardField] = $payload[$field];
                    break;
                }
            }
        }

        $card['cert_type'] = isset($card['cert_type']) && $card['cert_type'] !== '' ? $card['cert_type'] : '00';
        $card['cert_validity_type'] = isset($card['cert_validity_type']) && $card['cert_validity_type'] !== '' ? $card['cert_validity_type'] : '1';
        $card['cert_begin_date'] = isset($card['cert_begin_date']) && $card['cert_begin_date'] !== '' ? $card['cert_begin_date'] : date('Ymd');
        $this->assertRequired($card, ['card_type', 'card_name', 'card_no', 'cert_type', 'cert_no', 'cert_validity_type', 'cert_begin_date', 'mp'], 'Bank card params');
        if ((string)$card['cert_validity_type'] === '0' && empty($card['cert_end_date'])) {
            throw new EasyHuifuException('Bank card params missing required field: cert_end_date');
        }

        if ($isIndividual) {
            $this->assertRequired($card, ['prov_id', 'area_id'], 'Bank card params');
        } elseif (empty($card['branch_code']) && !empty($card['branch_name'])) {
            $resolver = $this->branchCodeResolver();
            $card['branch_code'] = trim((string)$resolver->resolve((string)$card['branch_name'], isset($card['bank_code']) ? (string)$card['bank_code'] : ''));
            if ($card['branch_code'] === '') {
                throw new EasyHuifuException('Enterprise bank card branch code could not be resolved from local dataset');
            }
        }

        return $card;
    }

    private function buildCashConfig(array $payload)
    {
        if (isset($payload['cash_config']) && is_array($payload['cash_config']) && !empty($payload['cash_config'])) {
            $cashConfig = $payload['cash_config'];
            foreach ($cashConfig as $index => $item) {
                if (is_array($item)) {
                    $cashConfig[$index]['cash_type'] = 'T1';
                }
            }
            return $cashConfig;
        }

        return [['cash_type' => 'T1', 'fix_amt' => '0.00']];
    }

    private function buildExtend(array $payload, array $fields)
    {
        $extend = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $payload) || $payload[$field] === '' || $payload[$field] === null) {
                continue;
            }
            $extend[$field] = is_array($payload[$field]) ? $this->normalizeJson($payload[$field]) : (string)$payload[$field];
        }
        return $extend;
    }

    private function buildEntryFormSnapshot($entryType, array $payload)
    {
        $data = [];
        $fields = $entryType === 'indv'
            ? ['name', 'cert_no', 'mobile_no', 'cert_type', 'cert_validity_type', 'cert_begin_date', 'cert_end_date', 'address']
            : ['reg_name', 'license_code', 'reg_prov_id', 'reg_area_id', 'reg_district_id', 'reg_detail', 'legal_name', 'legal_cert_no', 'contact_name', 'contact_mobile', 'license_validity_type', 'license_begin_date', 'license_end_date', 'legal_cert_validity_type', 'legal_cert_begin_date', 'legal_cert_end_date'];

        foreach ($fields as $field) {
            if (array_key_exists($field, $payload) && $payload[$field] !== '' && $payload[$field] !== null) {
                $data[$field] = is_scalar($payload[$field]) ? (string)$payload[$field] : $payload[$field];
            }
        }
        return $data;
    }

    private function filterPayloadByEntryType(array $payload, $entryType)
    {
        $commonFields = ['upper_huifu_id', 'ljh_data', 'hxy_data', 'settle_config', 'cash_config', 'card_info', 'file_list', 'delay_flag', 'elec_acct_config', 'open_tax_flag', 'async_return_url', 'lg_platform_type', 'expand_id', 'sms_send_flag', 'mcc'];
        $indvFields = ['name', 'cert_type', 'cert_no', 'cert_validity_type', 'cert_begin_date', 'cert_end_date', 'cert_nationality', 'mobile_no', 'address', 'email', 'login_name', 'prov_id', 'area_id', 'district_id'];
        $entFields = ['reg_name', 'license_code', 'license_validity_type', 'license_begin_date', 'license_end_date', 'reg_prov_id', 'reg_area_id', 'reg_district_id', 'reg_detail', 'legal_name', 'legal_cert_type', 'cert_no', 'legal_cert_no', 'legal_cert_validity_type', 'legal_cert_begin_date', 'legal_cert_end_date', 'legal_cert_nationality', 'contact_name', 'contact_mobile', 'login_name', 'short_name', 'contact_email', 'operator_id', 'ent_type'];
        $cardFields = ['card_type', 'card_name', 'card_no', 'prov_id', 'area_id', 'branch_code', 'branch_name', 'bank_code', 'cert_type', 'cert_no', 'cert_validity_type', 'cert_begin_date', 'cert_end_date', 'mp'];
        $allowFields = $entryType === 'ent' ? array_merge($commonFields, $entFields, $cardFields) : array_merge($commonFields, $indvFields, $cardFields);

        $filtered = [];
        foreach ($allowFields as $field) {
            if (array_key_exists($field, $payload)) {
                $filtered[$field] = $payload[$field];
            }
        }
        if (!isset($filtered['card_info']) || !is_array($filtered['card_info'])) {
            $filtered['card_info'] = [];
        }

        $card = [];
        foreach ($cardFields as $field) {
            if (array_key_exists($field, $filtered['card_info'])) {
                $card[$field] = $filtered['card_info'][$field];
            } elseif (array_key_exists($field, $filtered) && $filtered[$field] !== '' && $filtered[$field] !== null) {
                $card[$field] = $filtered[$field];
            }
        }
        $filtered['card_info'] = $card;

        if ($entryType === 'indv') {
            if (empty($filtered['mobile_no']) && !empty($payload['mobile'])) {
                $filtered['mobile_no'] = $payload['mobile'];
            }
            if (empty($filtered['name']) && !empty($payload['card_name'])) {
                $filtered['name'] = $payload['card_name'];
            }
        } else {
            if (empty($filtered['contact_mobile']) && !empty($payload['mobile_no'])) {
                $filtered['contact_mobile'] = $payload['mobile_no'];
            }
            if (empty($filtered['contact_name']) && !empty($payload['name'])) {
                $filtered['contact_name'] = $payload['name'];
            }
        }

        return $filtered;
    }

    private function resolveEntryType(array $payload, array $context = [])
    {
        if (!empty($context['entry_type'])) {
            return (string)$context['entry_type'];
        }
        if (!empty($payload['entry_type'])) {
            return (string)$payload['entry_type'];
        }
        if (!empty($payload['reg_name']) || !empty($payload['license_code'])) {
            return 'ent';
        }
        return 'indv';
    }

    private function isIndividualPayload(array $payload, $entryType = '')
    {
        if ($entryType === 'indv') {
            return true;
        }
        if ($entryType === 'ent') {
            return false;
        }
        return !empty($payload['name']) && !empty($payload['cert_no']) && !empty($payload['mobile_no']) && empty($payload['reg_name']);
    }
}
