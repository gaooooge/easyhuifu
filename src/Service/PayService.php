<?php

namespace EasyHuifu\Service;

use BsPaySdk\request\V2TradePaymentScanpayCloseRequest;
use BsPaySdk\request\V3TradePaymentJspayRequest;
use BsPaySdk\request\V3TradePaymentScanpayQueryRequest;
use EasyHuifu\Exception\EasyHuifuException;

class PayService extends BaseService
{
    public function create(array $payload)
    {
        return $this->jsPay($payload);
    }

    public function jsPay(array $payload)
    {
        $amount = isset($payload['amount']) ? (float)$payload['amount'] : (isset($payload['trans_amt']) ? (float)$payload['trans_amt'] : 0.0);
        if ($amount <= 0) {
            throw new EasyHuifuException('Huifu pay failed: invalid amount');
        }

        $notifyUrl = $this->firstNotEmpty([
            $payload['notify_url'] ?? null,
            $this->config()->get('notify_url', ''),
        ]);
        if ($notifyUrl === '') {
            throw new EasyHuifuException('Huifu pay failed: missing notify_url');
        }

        $request = new V3TradePaymentJspayRequest();
        $reqSeqId = $this->buildPayReqSeqId($payload);
        $request->setReqDate(date('Ymd'));
        $request->setReqSeqId($reqSeqId);
        $request->setHuifuId($this->resolveHuifuId($payload));
        $request->setGoodsDesc($this->resolveGoodsDesc($payload));
        $request->setTradeType($this->resolveTradeType($payload));
        $request->setTransAmt($this->formatAmount($amount));
        $request->setExtendInfo($this->buildPayExtendInfo($payload, $notifyUrl));

        $this->logger()->info('pay.start', $this->maskSensitive([
            'req_seq_id' => $reqSeqId,
            'huifu_id' => $request->getHuifuId(),
            'trade_type' => $request->getTradeType(),
            'amount' => $request->getTransAmt(),
        ]));

        $response = $this->request($request, 'Huifu jspay create');
        $payInfo = $this->extractPayInfo($response);

        return [
            'req_seq_id' => $reqSeqId,
            'req_date' => $request->getReqDate(),
            'huifu_id' => $request->getHuifuId(),
            'resp_code' => $this->extractRespCode($response),
            'resp_desc' => $this->extractRespDesc($response),
            'pay_info' => $payInfo,
            'response' => $response,
        ];
    }

    public function miniApp(array $payload)
    {
        $payload['trade_type'] = $payload['trade_type'] ?? 'T_MINIAPP';

        return $this->jsPay($payload);
    }

    public function query(array $payload)
    {
        $request = new V3TradePaymentScanpayQueryRequest();
        $request->setHuifuId($this->resolveHuifuId($payload));

        if (!empty($payload['out_ord_id'])) {
            $request->setOutOrdId(trim((string)$payload['out_ord_id']));
        }
        if (!empty($payload['org_hf_seq_id'])) {
            $request->setOrgHfSeqId(trim((string)$payload['org_hf_seq_id']));
        }
        if (!empty($payload['org_req_seq_id'])) {
            $request->setOrgReqSeqId(trim((string)$payload['org_req_seq_id']));
        }

        if (
            $request->getOutOrdId() === null
            && $request->getOrgHfSeqId() === null
            && $request->getOrgReqSeqId() === null
        ) {
            throw new EasyHuifuException('Huifu pay query failed: missing out_ord_id/org_hf_seq_id/org_req_seq_id');
        }

        if ($request->getOrgHfSeqId() === null || $request->getOrgHfSeqId() === '') {
            $request->setOrgReqDate($this->resolveOriginalReqDate($payload));
        } elseif (!empty($payload['org_req_date'])) {
            $request->setOrgReqDate(trim((string)$payload['org_req_date']));
        }

        $response = $this->request($request, 'Huifu pay query');

        return [
            'huifu_id' => $request->getHuifuId(),
            'resp_code' => $this->extractRespCode($response),
            'resp_desc' => $this->extractRespDesc($response),
            'pay_info' => $this->extractPayInfo($response),
            'response' => $response,
        ];
    }

    public function close(array $payload)
    {
        $request = new V2TradePaymentScanpayCloseRequest();
        $request->setReqDate(date('Ymd'));
        $request->setReqSeqId($this->buildCloseReqSeqId($payload));
        $request->setHuifuId($this->resolveHuifuId($payload));
        $request->setOrgReqDate($this->resolveOriginalReqDate($payload));

        $extend = [];
        if (!empty($payload['org_req_seq_id'])) {
            $extend['org_req_seq_id'] = trim((string)$payload['org_req_seq_id']);
        }
        if (!empty($payload['org_hf_seq_id'])) {
            $extend['org_hf_seq_id'] = trim((string)$payload['org_hf_seq_id']);
        }
        if (empty($extend)) {
            throw new EasyHuifuException('Huifu pay close failed: missing org_req_seq_id/org_hf_seq_id');
        }
        $request->setExtendInfo($extend);

        $response = $this->request($request, 'Huifu pay close');

        return [
            'req_seq_id' => $request->getReqSeqId(),
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
            throw new EasyHuifuException('Huifu pay failed: missing huifu_id');
        }

        return $huifuId;
    }

    private function resolveGoodsDesc(array $payload)
    {
        return $this->firstNotEmpty([
            $payload['goods_desc'] ?? null,
            $payload['subject'] ?? null,
            $payload['body'] ?? null,
            '订单支付',
        ]);
    }

    private function resolveTradeType(array $payload)
    {
        $tradeType = $this->firstNotEmpty([
            $payload['trade_type'] ?? null,
        ]);
        if ($tradeType !== '') {
            return $tradeType;
        }

        $paySource = strtolower(trim((string)($payload['pay_source'] ?? '')));
        if ($paySource === 'wx' || $paySource === 'wxapp' || $paySource === 'miniapp') {
            return 'T_MINIAPP';
        }
        if ($paySource === 'mp' || $paySource === 'jsapi') {
            return 'T_JSAPI';
        }
        if ($paySource === 'alipay') {
            return 'A_JSAPI';
        }

        return 'T_MINIAPP';
    }

    private function buildPayExtendInfo(array $payload, $notifyUrl)
    {
        $extend = [
            'notify_url' => $notifyUrl,
            'delay_acct_flag' => (string)($payload['delay_acct_flag'] ?? 'N'),
            'pay_scene' => (string)($payload['pay_scene'] ?? '02'),
        ];

        $remark = $this->firstNotEmpty([
            $payload['remark'] ?? null,
            $payload['order_no'] ?? null,
            $payload['out_trade_no'] ?? null,
            $payload['merchant_order_no'] ?? null,
        ]);
        if ($remark !== '') {
            $extend['remark'] = $remark;
        }

        foreach ([
            'time_expire', 'limit_pay_type', 'channel_no', 'fq_mer_discount_flag',
            'term_div_coupon_type', 'fee_sign', 'acct_id',
        ] as $field) {
            if (isset($payload[$field]) && $payload[$field] !== '') {
                $extend[$field] = (string)$payload[$field];
            }
        }

        foreach ([
            'wx_data', 'alipay_data', 'unionpay_data', 'dc_data',
            'risk_check_data', 'terminal_device_data', 'combinedpay_data',
            'combinedpay_data_fee_info', 'trans_fee_allowance_info', 'acct_split_bunch',
        ] as $field) {
            if (!isset($payload[$field]) || $payload[$field] === '' || $payload[$field] === null) {
                continue;
            }
            $extend[$field] = is_array($payload[$field])
                ? $this->normalizeJson($payload[$field])
                : (string)$payload[$field];
        }

        if (!isset($extend['wx_data'])) {
            $wxData = $this->buildWxData($payload);
            if (!empty($wxData)) {
                $extend['wx_data'] = $this->normalizeJson($wxData);
            }
        }

        return $extend;
    }

    private function buildWxData(array $payload)
    {
        $wxData = [];
        foreach (['sub_appid', 'sub_openid', 'attach', 'body', 'device_info', 'goods_tag', 'identity', 'receipt', 'spbill_create_ip', 'promotion_flag', 'product_id', 'limit_payer'] as $field) {
            if (isset($payload[$field]) && $payload[$field] !== '' && $payload[$field] !== null) {
                $wxData[$field] = (string)$payload[$field];
            }
        }

        foreach (['detail', 'scene_info'] as $field) {
            if (!isset($payload[$field]) || $payload[$field] === '' || $payload[$field] === null) {
                continue;
            }
            $wxData[$field] = is_array($payload[$field]) ? $payload[$field] : json_decode((string)$payload[$field], true);
            if (!is_array($wxData[$field])) {
                unset($wxData[$field]);
            }
        }

        return $wxData;
    }

    private function extractPayInfo(array &$response)
    {
        if (!isset($response['data']) || !is_array($response['data']) || !isset($response['data']['pay_info'])) {
            return [];
        }

        $payInfo = $response['data']['pay_info'];
        if (is_array($payInfo)) {
            return $payInfo;
        }
        if (!is_string($payInfo) || trim($payInfo) === '') {
            return [];
        }

        $decoded = json_decode($payInfo, true);
        if (is_array($decoded)) {
            $response['data']['pay_info'] = $decoded;

            return $decoded;
        }

        return [];
    }

    private function buildPayReqSeqId(array $payload)
    {
        if (!empty($payload['req_seq_id'])) {
            return trim((string)$payload['req_seq_id']);
        }

        return 'rQ' . date('YmdHis') . mt_rand(1000000000000000, 9999999999999999);
    }

    private function buildCloseReqSeqId(array $payload)
    {
        if (!empty($payload['req_seq_id'])) {
            return trim((string)$payload['req_seq_id']);
        }

        return 'rC' . date('YmdHis') . mt_rand(1000000000000000, 9999999999999999);
    }

    private function resolveOriginalReqDate(array $payload)
    {
        if (!empty($payload['org_req_date'])) {
            return trim((string)$payload['org_req_date']);
        }

        $orgReqSeqId = $this->firstNotEmpty([
            $payload['org_req_seq_id'] ?? null,
            $payload['req_seq_id'] ?? null,
        ]);
        if (preg_match('/^\D*(\d{8})\d+$/', $orgReqSeqId, $matches)) {
            return $matches[1];
        }

        if (!empty($payload['pay_time'])) {
            return date('Ymd', (int)$payload['pay_time']);
        }

        if (!empty($payload['order_date'])) {
            $timestamp = strtotime((string)$payload['order_date']);
            if ($timestamp !== false) {
                return date('Ymd', $timestamp);
            }
        }

        return date('Ymd');
    }
}
