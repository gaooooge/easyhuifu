<?php

namespace EasyHuifu\Service;

use BsPaySdk\request\V2TradeAcctpaymentPayRequest;
use EasyHuifu\Exception\EasyHuifuException;

class PayoutService extends BaseService
{
    public function payToActor(array $payload)
    {
        $roleType = isset($payload['role_type']) ? trim((string)$payload['role_type']) : '';
        $actorId = isset($payload['actor_id']) ? (int)$payload['actor_id'] : 0;
        $appId = isset($payload['app_id']) ? (int)$payload['app_id'] : 0;
        $amount = isset($payload['amount']) ? (float)$payload['amount'] : 0.0;

        if ($amount <= 0) {
            throw new EasyHuifuException('Huifu payout failed: invalid amount');
        }

        $payeeHuifuId = $this->resolvePayeeHuifuId($payload, $appId, $roleType, $actorId);
        $outHuifuId = trim((string)($payload['out_huifu_id'] ?? $this->config()->getRequired('sys_id')));
        $reqSeqId = $this->buildReqSeqId('acp', $payload);
        $ordAmt = $this->formatAmount($amount);
        $remark = isset($payload['remark']) ? trim((string)$payload['remark']) : 'Huifu account payout';

        $request = new V2TradeAcctpaymentPayRequest();
        $request->setReqSeqId($reqSeqId);
        $request->setReqDate(date('Ymd'));
        $request->setOutHuifuId($outHuifuId);
        $request->setOrdAmt($ordAmt);
        $request->setAcctSplitBunch($this->buildAcctSplitBunch($payeeHuifuId, $ordAmt));
        $request->setRiskCheckData($this->buildRiskCheckData());
        $request->setExtendInfo([
            'remark' => $remark,
            'good_desc' => $remark,
        ]);

        $this->logger()->info('payout.start', [
            'req_seq_id' => $reqSeqId,
            'out_huifu_id' => $outHuifuId,
            'payee_huifu_id' => $payeeHuifuId,
            'amount' => $ordAmt,
            'role_type' => $roleType,
            'actor_id' => $actorId,
            'app_id' => $appId,
        ]);

        $response = $this->request($request, 'Huifu account payout');

        return [
            'req_seq_id' => $reqSeqId,
            'payee_huifu_id' => $payeeHuifuId,
            'resp_code' => $this->extractRespCode($response),
            'resp_desc' => $this->extractRespDesc($response),
            'response' => $response,
        ];
    }

    private function resolvePayeeHuifuId(array $payload, $appId, $roleType, $actorId)
    {
        if (!empty($payload['huifu_id'])) {
            return trim((string)$payload['huifu_id']);
        }

        if ($roleType === '' || $actorId <= 0) {
            throw new EasyHuifuException('Huifu payout failed: missing role_type/actor_id or huifu_id');
        }

        $repository = $this->entryRepository();
        if ($repository === null) {
            throw new EasyHuifuException('Huifu payout failed: entry repository is required when huifu_id is not provided');
        }

        $entry = $this->normalizeRecord($repository->findSuccessEntry($appId, $roleType, $actorId));
        if (!$entry || empty($entry['huifu_id'])) {
            throw new EasyHuifuException('Huifu payout failed: payee onboarding record was not found');
        }

        return trim((string)$entry['huifu_id']);
    }

    private function buildAcctSplitBunch($payeeHuifuId, $amount)
    {
        return json_encode([
            'acct_infos' => [
                [
                    'huifu_id' => (string)$payeeHuifuId,
                    'div_amt' => (string)$amount,
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function buildRiskCheckData()
    {
        return json_encode([
            'transfer_type' => '04',
            'sub_product' => '1',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
