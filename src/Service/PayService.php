<?php

namespace EasyHuifu\Service;

use BsPaySdk\request\v4\payment\TradePaymentCreateRequest;
use BsPaySdk\request\V2TradePaymentScanpayCloseRequest;
use BsPaySdk\request\V3TradePaymentJspayRequest;
use BsPaySdk\request\V3TradePaymentScanpayQueryRequest;
use EasyHuifu\Exception\EasyHuifuException;

class PayService extends BaseService
{
    public function create(array $payload)
    {
        if ($this->shouldUseUnifiedCreate($payload)) {
            return $this->unifiedCreate($payload);
        }

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

    public function app(array $payload)
    {
        $payload['trade_type'] = $payload['trade_type'] ?? $this->resolveAppTradeType($payload);

        return $this->unifiedCreate($payload);
    }

    public function alipay(array $payload)
    {
        $payload['trade_type'] = $payload['trade_type'] ?? 'A_JSAPI';

        if ($this->shouldUseUnifiedCreate($payload)) {
            return $this->unifiedCreate($payload);
        }

        return $this->jsPay($payload);
    }

    public function alipayNative(array $payload)
    {
        $payload['trade_type'] = $payload['trade_type'] ?? 'A_NATIVE';

        return $this->unifiedCreate($payload);
    }

    public function alipayApp(array $payload)
    {
        return $this->alipayNative($payload);
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
        if ($paySource === 'app') {
            return 'T_APP';
        }
        if ($paySource === 'alipay') {
            return 'A_JSAPI';
        }
        if ($paySource === 'alipay_native' || $paySource === 'native' || $paySource === 'app_alipay' || $paySource === 'alipay_app' || $paySource === 'appzfb') {
            return 'A_NATIVE';
        }

        return 'T_MINIAPP';
    }

    private function resolveAppTradeType(array $payload)
    {
        $paySource = strtolower(trim((string)($payload['pay_source'] ?? '')));
        if (in_array($paySource, ['alipay', 'alipay_native', 'native', 'app_alipay', 'alipay_app', 'appzfb'], true)) {
            return 'A_NATIVE';
        }

        return 'T_APP';
    }

    private function shouldUseUnifiedCreate(array $payload)
    {
        $tradeType = strtoupper($this->resolveTradeType($payload));
        if (in_array($tradeType, ['T_APP', 'A_NATIVE', 'U_NATIVE', 'U_JSAPI', 'U_MICROPAY'], true)) {
            return true;
        }

        $paySource = strtolower(trim((string)($payload['pay_source'] ?? '')));

        return in_array($paySource, ['app', 'native', 'alipay_native', 'app_alipay', 'alipay_app', 'appzfb'], true);
    }

    private function unifiedCreate(array $payload)
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

        $request = new TradePaymentCreateRequest();
        $reqSeqId = $this->buildPayReqSeqId($payload);
        $tradeType = strtoupper($this->resolveTradeType($payload));

        $request->setReqDate(date('Ymd'));
        $request->setReqSeqId($reqSeqId);
        $request->setHuifuId($this->resolveHuifuId($payload));
        $request->setGoodsDesc($this->resolveGoodsDesc($payload));
        $request->setTradeType($tradeType);
        $request->setTransAmt($this->formatAmount($amount));
        $request->setNotifyUrl($notifyUrl);
        $request->setDelayAcctFlag((string)($payload['delay_acct_flag'] ?? 'N'));
        $request->setPayScene((string)($payload['pay_scene'] ?? '02'));

        $remark = $this->firstNotEmpty([
            $payload['remark'] ?? null,
            $payload['order_no'] ?? null,
            $payload['out_trade_no'] ?? null,
            $payload['merchant_order_no'] ?? null,
        ]);
        if ($remark !== '') {
            $request->setRemark($remark);
        }

        foreach ([
            'time_expire' => 'setTimeExpire',
            'limit_pay_type' => 'setLimitPayType',
            'channel_no' => 'setChannelNo',
            'acct_id' => 'setAcctId',
            'term_div_coupon_type' => 'setTermDivCouponType',
            'fq_mer_discount_flag' => 'setFqMerDiscountFlag',
            'combinedpay_data' => 'setCombinedpayData',
            'combinedpay_data_fee_info' => 'setCombinedpayDataFeeInfo',
            'trans_fee_allowance_info' => 'setTransFeeAllowanceInfo',
        ] as $field => $setter) {
            if (!isset($payload[$field]) || $payload[$field] === '' || $payload[$field] === null) {
                continue;
            }

            $value = is_array($payload[$field]) ? $this->normalizeJson($payload[$field]) : (string)$payload[$field];
            $request->{$setter}($value);
        }

        $feeFlag = $this->firstNotEmpty([
            $payload['fee_flag'] ?? null,
            $payload['fee_sign'] ?? null,
        ]);
        if ($feeFlag !== '') {
            $request->setFeeFlag($feeFlag);
        }

        if (!empty($payload['acct_split_bunch'])) {
            $request->setAcctSplitBunch(is_array($payload['acct_split_bunch'])
                ? $this->normalizeJson($payload['acct_split_bunch'])
                : (string)$payload['acct_split_bunch']);
        }
        if (!empty($payload['terminal_device_data'])) {
            $request->setTerminalDeviceData(is_array($payload['terminal_device_data'])
                ? $this->normalizeJson($payload['terminal_device_data'])
                : (string)$payload['terminal_device_data']);
        }

        $methodExpand = $this->buildMethodExpand($tradeType, $payload);
        if ($methodExpand !== '') {
            $request->setMethodExpand($methodExpand);
        }

        $this->logger()->info('pay.start', $this->maskSensitive([
            'req_seq_id' => $reqSeqId,
            'huifu_id' => $request->getHuifuId(),
            'trade_type' => $tradeType,
            'amount' => $request->getTransAmt(),
        ]));

        $response = $this->request($request, 'Huifu unified create');
        $payInfo = $this->extractPayInfo($response);

        return [
            'req_seq_id' => $reqSeqId,
            'req_date' => $request->getReqDate(),
            'huifu_id' => $request->getHuifuId(),
            'trade_type' => $tradeType,
            'resp_code' => $this->extractRespCode($response),
            'resp_desc' => $this->extractRespDesc($response),
            'pay_info' => $payInfo,
            'qr_code' => $this->extractResponseValue($response, 'qr_code'),
            'hf_seq_id' => $this->extractResponseValue($response, 'hf_seq_id'),
            'response' => $response,
        ];
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

    private function buildMethodExpand($tradeType, array $payload)
    {
        $tradeType = strtoupper((string)$tradeType);

        if (!empty($payload['method_expand'])) {
            return is_array($payload['method_expand'])
                ? $this->normalizeJson($payload['method_expand'])
                : trim((string)$payload['method_expand']);
        }

        if (strpos($tradeType, 'A_') === 0) {
            if (!empty($payload['alipay_data'])) {
                return is_array($payload['alipay_data'])
                    ? $this->normalizeJson($payload['alipay_data'])
                    : trim((string)$payload['alipay_data']);
            }

            $alipayData = $this->buildAlipayData($payload);

            return empty($alipayData) ? '' : $this->normalizeJson($alipayData);
        }

        if (strpos($tradeType, 'T_') === 0 || strpos($tradeType, 'U_') === 0) {
            if (!empty($payload['wx_data'])) {
                return is_array($payload['wx_data'])
                    ? $this->normalizeJson($payload['wx_data'])
                    : trim((string)$payload['wx_data']);
            }

            $wxData = $this->buildWxData($payload);

            return empty($wxData) ? '' : $this->normalizeJson($wxData);
        }

        return '';
    }

    private function buildAlipayData(array $payload)
    {
        $alipayData = [];
        foreach ([
            'alipay_store_id', 'buyer_id', 'buyer_logon_id', 'auth_code', 'seller_id',
            'merchant_order_no', 'operator_id', 'product_code', 'subject', 'store_name',
            'op_app_id', 'body',
        ] as $field) {
            if (isset($payload[$field]) && $payload[$field] !== '' && $payload[$field] !== null) {
                $alipayData[$field] = (string)$payload[$field];
            }
        }

        foreach (['goods_detail', 'extend_params', 'ali_promo_params', 'ext_user_info', 'ali_business_params'] as $field) {
            if (!isset($payload[$field]) || $payload[$field] === '' || $payload[$field] === null) {
                continue;
            }

            if (is_array($payload[$field])) {
                $alipayData[$field] = $payload[$field];
                continue;
            }

            $decoded = json_decode((string)$payload[$field], true);
            if (is_array($decoded)) {
                $alipayData[$field] = $decoded;
            }
        }

        return $alipayData;
    }

    private function extractPayInfo(array &$response)
    {
        $payInfo = $this->extractResponseValue($response, 'pay_info');
        if ($payInfo === null) {
            return [];
        }
        if (is_array($payInfo)) {
            return $payInfo;
        }
        if (!is_string($payInfo) || trim($payInfo) === '') {
            return [];
        }

        $decoded = json_decode($payInfo, true);
        if (is_array($decoded)) {
            if (isset($response['data']) && is_array($response['data']) && array_key_exists('pay_info', $response['data'])) {
                $response['data']['pay_info'] = $decoded;
            } else {
                $response['pay_info'] = $decoded;
            }

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

    private function extractResponseValue(array $response, $key)
    {
        if (array_key_exists($key, $response)) {
            return $response[$key];
        }

        if (isset($response['data']) && is_array($response['data']) && array_key_exists($key, $response['data'])) {
            return $response['data'][$key];
        }

        return null;
    }
}
