<?php

namespace EasyHuifu\Service;

use EasyHuifu\Application;
use EasyHuifu\Exception\EasyHuifuException;

abstract class BaseService
{
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    protected function application()
    {
        return $this->app;
    }

    protected function config()
    {
        return $this->app->config();
    }

    protected function logger()
    {
        return $this->app->logger();
    }

    protected function clientFactory()
    {
        return $this->app->clientFactory();
    }

    protected function entryRepository()
    {
        return $this->app->entryRepository();
    }

    protected function branchCodeResolver()
    {
        return $this->app->branchCodeResolver();
    }

    protected function request($request, $actionName)
    {
        $this->logger()->info('request.send', $this->maskSensitive([
            'action' => $actionName,
            'request_class' => is_object($request) ? get_class($request) : gettype($request),
        ]));

        $client = $this->clientFactory()->create();
        $result = $client->postRequest($request);
        if (!$result || $result->isError()) {
            $error = $result ? $result->getErrorInfo() : [];
            $message = is_array($error) && isset($error['msg']) ? (string)$error['msg'] : ($actionName . ' failed');

            $this->logger()->error('request.error', $this->maskSensitive([
                'action' => $actionName,
                'error' => $error,
                'message' => $message,
            ]));

            throw new EasyHuifuException($message);
        }

        $response = $result->getRspDatas();
        if (!is_array($response)) {
            $this->logger()->error('request.invalid_response', [
                'action' => $actionName,
                'response' => $response,
            ]);

            throw new EasyHuifuException($actionName . ' returned an invalid response');
        }

        $respCode = $this->extractRespCode($response);
        $respDesc = $this->extractRespDesc($response);
        if ($respCode !== '' && !in_array($respCode, ['00000100', '00000000'], true)) {
            $this->logger()->error('request.business_error', $this->maskSensitive([
                'action' => $actionName,
                'resp_code' => $respCode,
                'resp_desc' => $respDesc,
                'response' => $response,
            ]));

            throw new EasyHuifuException($respDesc !== '' ? $respDesc : ($actionName . ' failed'));
        }

        $this->logger()->info('request.success', [
            'action' => $actionName,
            'resp_code' => $respCode,
            'resp_desc' => $respDesc,
        ]);

        return $response;
    }

    protected function extractRespCode(array $response)
    {
        if (isset($response['resp_code'])) {
            return (string)$response['resp_code'];
        }

        if (isset($response['data']) && is_array($response['data']) && isset($response['data']['resp_code'])) {
            return (string)$response['data']['resp_code'];
        }

        return '';
    }

    protected function extractRespDesc(array $response)
    {
        if (isset($response['resp_desc'])) {
            return (string)$response['resp_desc'];
        }

        if (isset($response['data']) && is_array($response['data']) && isset($response['data']['resp_desc'])) {
            return (string)$response['data']['resp_desc'];
        }

        return '';
    }

    protected function extractHuifuId(array $response)
    {
        if (isset($response['data']) && is_array($response['data'])) {
            if (!empty($response['data']['huifu_id'])) {
                return (string)$response['data']['huifu_id'];
            }
            if (!empty($response['data']['user_huifu_id'])) {
                return (string)$response['data']['user_huifu_id'];
            }
        }

        if (!empty($response['huifu_id'])) {
            return (string)$response['huifu_id'];
        }
        if (!empty($response['user_huifu_id'])) {
            return (string)$response['user_huifu_id'];
        }

        return '';
    }

    protected function buildReqSeqId($step, array $context = [])
    {
        $role = isset($context['role_type']) ? (string)$context['role_type'] : 'user';
        $role = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $role), 0, 6));
        if ($role === '') {
            $role = 'USER';
        }

        $step = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', (string)$step), 0, 6));
        if ($step === '') {
            $step = 'REQ';
        }

        return $role . $step . date('YmdHis') . mt_rand(100000, 999999);
    }

    protected function buildLoginName(array $context = [])
    {
        $role = isset($context['role_type']) ? (string)$context['role_type'] : 'user';
        $actorId = isset($context['actor_id']) ? (string)$context['actor_id'] : '0';

        return 'LG' . strtoupper(substr($role, 0, 2)) . date('YmdHis') . $actorId;
    }

    protected function assertRequired(array $payload, array $required, $scene)
    {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($payload[$field]) || $payload[$field] === '' || $payload[$field] === null) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new EasyHuifuException($scene . ' missing required fields: ' . implode(',', $missing));
        }
    }

    protected function normalizeJson($value)
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string)$value;
    }

    protected function formatAmount($amount)
    {
        return number_format((float)$amount, 2, '.', '');
    }

    protected function normalizeRecord($record)
    {
        if ($record === null || is_array($record)) {
            return $record;
        }

        if (is_object($record)) {
            if (method_exists($record, 'toArray')) {
                return $record->toArray();
            }
            if ($record instanceof \JsonSerializable) {
                return (array)$record->jsonSerialize();
            }

            return get_object_vars($record);
        }

        return null;
    }

    protected function firstNotEmpty(array $candidates)
    {
        foreach ($candidates as $value) {
            if ($value === null) {
                continue;
            }

            $candidate = is_scalar($value) ? trim((string)$value) : '';
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    protected function maskSensitive($value, $field = '')
    {
        if (is_array($value)) {
            $masked = [];
            foreach ($value as $key => $item) {
                $masked[$key] = $this->maskSensitive($item, is_string($key) ? $key : '');
            }
            return $masked;
        }

        if (!is_string($value)) {
            return $value;
        }

        $sensitiveFields = [
            'cert_no', 'legal_cert_no', 'card_no', 'mp', 'mobile_no', 'contact_mobile',
            'rsa_merch_private_key', 'rsa_huifu_public_key', 'rsa_private', 'rsa_public',
        ];

        if ($field !== '' && in_array(strtolower($field), $sensitiveFields, true)) {
            return $this->maskString($value);
        }

        return $value;
    }

    protected function maskString($value)
    {
        $length = strlen($value);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 2) . str_repeat('*', max($length - 4, 1)) . substr($value, -2);
    }
}
