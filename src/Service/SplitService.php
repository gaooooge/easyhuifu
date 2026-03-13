<?php

namespace EasyHuifu\Service;

use BsPaySdk\request\V2TradePaymentDelaytransConfirmRequest;
use BsPaySdk\request\V2TradePaymentDelaytransConfirmrefundRequest;
use BsPaySdk\request\V2TradePaymentDelaytransConfirmqueryRequest;
use EasyHuifu\Exception\EasyHuifuException;

class SplitService extends BaseService
{
    public function confirm(array $payload)
    {
        $request = new V2TradePaymentDelaytransConfirmRequest();
        $request->setReqDate(date('Ymd'));
        $request->setReqSeqId($this->buildConfirmReqSeqId($payload));
        $request->setHuifuId($this->resolveHuifuId($payload));
        $request->setPayType($this->resolvePayType($payload));

        $extend = [];
        $orgReqSeqId = isset($payload['org_req_seq_id']) ? trim((string)$payload['org_req_seq_id']) : '';
        $orgHfSeqId = isset($payload['org_hf_seq_id']) ? trim((string)$payload['org_hf_seq_id']) : '';
        $orgMerOrdId = isset($payload['org_mer_ord_id']) ? trim((string)$payload['org_mer_ord_id']) : '';
        if ($orgReqSeqId === '' && $orgHfSeqId === '' && $orgMerOrdId === '') {
            throw new EasyHuifuException('Huifu split confirm failed: missing org_req_seq_id/org_hf_seq_id/org_mer_ord_id');
        }

        $orgReqDate = $this->resolveOriginalReqDate($payload, $orgReqSeqId);
        if ($orgReqDate !== '') {
            $extend['org_req_date'] = $orgReqDate;
        }
        if ($orgReqSeqId !== '') {
            $extend['org_req_seq_id'] = $orgReqSeqId;
        }
        if ($orgHfSeqId !== '') {
            $extend['org_hf_seq_id'] = $orgHfSeqId;
        }
        if ($orgMerOrdId !== '') {
            $extend['org_mer_ord_id'] = $orgMerOrdId;
        }

        if (!isset($payload['acct_split_bunch']) || $payload['acct_split_bunch'] === '' || $payload['acct_split_bunch'] === null) {
            throw new EasyHuifuException('Huifu split confirm failed: missing acct_split_bunch');
        }
        $extend['acct_split_bunch'] = $this->normalizeJson($payload['acct_split_bunch']);

        foreach (['remark', 'hyc_flag', 'lg_platform_type', 'salary_modle_type', 'bmember_id', 'notify_url'] as $field) {
            if (isset($payload[$field]) && $payload[$field] !== '' && $payload[$field] !== null) {
                $extend[$field] = (string)$payload[$field];
            }
        }
        foreach (['risk_check_data', 'ljh_data'] as $field) {
            if (isset($payload[$field]) && $payload[$field] !== '' && $payload[$field] !== null) {
                $extend[$field] = is_array($payload[$field]) ? $this->normalizeJson($payload[$field]) : (string)$payload[$field];
            }
        }

        $request->setExtendInfo($extend);
        $response = $this->request($request, 'Huifu split confirm');

        return [
            'req_seq_id' => $request->getReqSeqId(),
            'req_date' => $request->getReqDate(),
            'huifu_id' => $request->getHuifuId(),
            'resp_code' => $this->extractRespCode($response),
            'resp_desc' => $this->extractRespDesc($response),
            'response' => $response,
        ];
    }

    public function confirmQuery(array $payload)
    {
        $request = new V2TradePaymentDelaytransConfirmqueryRequest();
        $request->setHuifuId($this->resolveHuifuId($payload));

        $orgReqSeqId = isset($payload['org_req_seq_id']) ? trim((string)$payload['org_req_seq_id']) : '';
        if ($orgReqSeqId === '') {
            throw new EasyHuifuException('Huifu split confirm query failed: missing org_req_seq_id');
        }

        $request->setOrgReqSeqId($orgReqSeqId);
        $request->setOrgReqDate($this->resolveOriginalReqDate($payload, $orgReqSeqId));

        $response = $this->request($request, 'Huifu split confirm query');

        return [
            'huifu_id' => $request->getHuifuId(),
            'org_req_seq_id' => $request->getOrgReqSeqId(),
            'org_req_date' => $request->getOrgReqDate(),
            'resp_code' => $this->extractRespCode($response),
            'resp_desc' => $this->extractRespDesc($response),
            'response' => $response,
        ];
    }

    public function confirmRefund(array $payload)
    {
        $request = new V2TradePaymentDelaytransConfirmrefundRequest();
        $request->setReqDate(date('Ymd'));
        $request->setReqSeqId($this->buildConfirmRefundReqSeqId($payload));
        $request->setHuifuId($this->resolveHuifuId($payload));

        $orgReqSeqId = isset($payload['org_req_seq_id']) ? trim((string)$payload['org_req_seq_id']) : '';
        if ($orgReqSeqId === '') {
            throw new EasyHuifuException('Huifu split confirm refund failed: missing org_req_seq_id');
        }

        $orgReqDate = $this->resolveOriginalReqDate($payload, $orgReqSeqId);
        $request->setOrgReqSeqId($orgReqSeqId);
        $request->setOrgReqDate($orgReqDate);

        $extend = [];
        foreach (['loan_flag', 'loan_undertaker', 'loan_acct_type', 'remark'] as $field) {
            if (isset($payload[$field]) && $payload[$field] !== '' && $payload[$field] !== null) {
                $extend[$field] = (string)$payload[$field];
            }
        }
        if (isset($payload['acct_split_bunch']) && $payload['acct_split_bunch'] !== '' && $payload['acct_split_bunch'] !== null) {
            $extend['acct_split_bunch'] = $this->normalizeJson($payload['acct_split_bunch']);
        }
        if (!empty($extend)) {
            $request->setExtendInfo($extend);
        }

        $response = $this->request($request, 'Huifu split confirm refund');

        return [
            'req_seq_id' => $request->getReqSeqId(),
            'req_date' => $request->getReqDate(),
            'huifu_id' => $request->getHuifuId(),
            'org_req_seq_id' => $request->getOrgReqSeqId(),
            'org_req_date' => $request->getOrgReqDate(),
            'resp_code' => $this->extractRespCode($response),
            'resp_desc' => $this->extractRespDesc($response),
            'response' => $response,
        ];
    }

    private function resolveHuifuId(array $payload)
    {
        $huifuId = $this->firstNotEmpty([
            $payload['huifu_id'] ?? null,
            $this->config()->get('sys_id', ''),
        ]);
        if ($huifuId === '') {
            throw new EasyHuifuException('Huifu split confirm failed: missing huifu_id');
        }

        return $huifuId;
    }

    private function resolvePayType(array $payload)
    {
        $payType = isset($payload['pay_type']) ? trim((string)$payload['pay_type']) : '';
        if ($payType !== '') {
            return $payType;
        }

        return 'ACCT_PAYMENT';
    }

    private function buildConfirmReqSeqId(array $payload)
    {
        if (!empty($payload['req_seq_id'])) {
            return trim((string)$payload['req_seq_id']);
        }

        return 'rS' . date('YmdHis') . mt_rand(1000000000000000, 9999999999999999);
    }

    private function buildConfirmRefundReqSeqId(array $payload)
    {
        if (!empty($payload['req_seq_id'])) {
            return trim((string)$payload['req_seq_id']);
        }

        return 'rSF' . date('YmdHis') . mt_rand(1000000000000000, 9999999999999999);
    }

    private function resolveOriginalReqDate(array $payload, $orgReqSeqId = '')
    {
        if (!empty($payload['org_req_date'])) {
            return trim((string)$payload['org_req_date']);
        }

        $orgReqSeqId = trim((string)$orgReqSeqId);
        if (preg_match('/^\D*(\d{8})\d+$/', $orgReqSeqId, $matches)) {
            return $matches[1];
        }

        if (!empty($payload['pay_time'])) {
            return date('Ymd', (int)$payload['pay_time']);
        }

        return date('Ymd');
    }
}
