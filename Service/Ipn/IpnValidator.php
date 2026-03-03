<?php

declare(strict_types=1);

namespace SimPay\Magento\Service\Ipn;

use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\Framework\Phrase;

class IpnValidator
{
    public function __construct(
        private readonly Request $request
    ) {}

    public function validate(array $payload, string $expectedServiceId, string $ipnKey): void
    {
        // Version check from UA: "SimPay-IPN/2.0"
        $ua = (string)($this->request->getServer('HTTP_USER_AGENT') ?? '');
        $parts = explode('/', $ua, 2);
        $version = $parts[1] ?? '';

        if ($version !== '2.0') {
            throw new \RuntimeException('IPN version is not supported (v: ' . ($version ?: 'N/A') . ')');
        }

        foreach (['type', 'notification_id', 'date', 'data', 'signature'] as $key) {
            if (!isset($payload[$key]) || $payload[$key] === '' || $payload[$key] === []) {
                throw new \RuntimeException('Invalid payload - missing required field: ' . $key);
            }
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        if (isset($data['service_id']) && (string)$data['service_id'] !== $expectedServiceId) {
            throw new \RuntimeException('Invalid service_id');
        }

        if (!$this->isValidSignature($payload, $ipnKey)) {
            throw new \RuntimeException('Invalid signature');
        }
    }

    private function isValidSignature(array $payload, string $ipnKey): bool
    {
        $flat = $this->flattenArray($payload);
        $flat[] = $ipnKey;

        $signature = hash('sha256', implode('|', $flat));

        return hash_equals($signature, (string)($payload['signature'] ?? ''));
    }

    private function flattenArray(array $array): array
    {
        unset($array['signature']);

        $out = [];
        array_walk_recursive($array, static function ($value) use (&$out): void {
            $out[] = $value;
        });

        // Ensure scalar strings only (stable hashing)
        return array_map(static fn($v) => is_scalar($v) ? (string)$v : '', $out);
    }
}