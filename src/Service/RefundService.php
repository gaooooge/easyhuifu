<?php

namespace EasyHuifu\Service;

use BsPaySdk\request\V3TradePaymentScanpayRefundRequest;
use EasyHuifu\Exception\EasyHuifuException;

class RefundService extends BaseService
{
    public function refund(array $order, $money, $outRefundNo = '')
    {
        return $this->scanPay($order, $money, $outRefundNo);
    }

    public function scanPay(array $order, $money, $outRefundNo = '')
    {
        $amount = (float)$money;
        if ($amount <= 0) {
            throw new EasyHuifuException('Huifu refund failed: invalid amount');
        }

        $request = new V3TradePaymentScanpayRefundRequest();
        $request->setReqDate(date('Ymd'));
        $request->setReqSeqId($this->buildRefundReqSeqId($outRefundNo));
        $request->setHuifuId((string)($order['huifu_id'] ?? $this->config()->getRequired('sys_id')));
        $request->setOrdAmt($this->formatAmount($amount));

        $orgReqSeqId = $this->resolveOrgReqSeqId($order);
        $orgReqDate = $this->resolveOrgReqDate($order, $orgReqSeqId);
        $orgHfSeqId = isset($order['transaction_id']) ? trim((string)$order['transaction_id']) : '';
        $request->setOrgReqDate($orgReqDate);

        $extend = [];
        if ($orgReqSeqId !== '') {
            $extend['org_req_seq_id'] = $orgReqSeqId;
        }
        if ($orgHfSeqId !== '') {
            $extend['org_hf_seq_id'] = $orgHfSeqId;
        }
        if (empty($extend)) {
            throw new EasyHuifuException('Huifu refund failed: missing original request identifiers');
        }
        $request->setExtendInfo($extend);

        $response = $this->request($request, 'Huifu scanpay refund');

        return [
            'req_seq_id' => $request->getReqSeqId(),
            'resp_code' => $this->extractRespCode($response),
            'resp_desc' => $this->extractRespDesc($response),
            'response' => $response,
        ];
    }

    private function buildRefundReqSeqId($outRefundNo)
    {
        $outRefundNo = trim((string)$outRefundNo);
        if ($outRefundNo !== '') {
            return $outRefundNo;
        }

        return 'rR' . date('YmdHis') . mt_rand(1000000000000000, 9999999999999999);
    }

    private function resolveOrgReqSeqId(array $order)
    {
        if (!empty($order['huifu_req_seq_id'])) {
            return trim((string)$order['huifu_req_seq_id']);
        }
        if (!empty($order['req_seq_id'])) {
            return trim((string)$order['req_seq_id']);
        }

        return '';
    }

    private function resolveOrgReqDate(array $order, $orgReqSeqId)
    {
        $orgReqSeqId = trim((string)$orgReqSeqId);
        if (strlen($orgReqSeqId) >= 10 && substr($orgReqSeqId, 0, 2) === 'rQ') {
            $date = substr($orgReqSeqId, 2, 8);
            if (preg_match('/^\d{8}$/', $date)) {
                return $date;
            }
        }

        if (!empty($order['org_req_date'])) {
            return trim((string)$order['org_req_date']);
        }
        if (!empty($order['pay_time'])) {
            return date('Ymd', (int)$order['pay_time']);
        }

        return date('Ymd');
    }
}
