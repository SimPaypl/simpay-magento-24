<?php

declare(strict_types=1);

namespace SimPay\Magento\Service;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class IpAllowlistService
{
    private const API_URL = 'https://api.simpay.pl/ip';
    private const CACHE_KEY = 'simpay_ip_allowlist';
    private const CACHE_TTL = 3600; // 1h

    public function __construct(
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger
    ) {}

    public function isAllowed(string $ip): bool
    {
        if ($ip === '') {
            return false;
        }

        try {
            $allowedIps = $this->getAllowedIps();

            if (empty($allowedIps)) {
                $this->logger->warning('SimPay IP allowlist empty - allowing request');
                return true;
            }

            return in_array($ip, $allowedIps, true);
        } catch (\Throwable $e) {
            $this->logger->error('SimPay IP allowlist check failed', [
                'error' => $e->getMessage(),
            ]);

            return true;
        }
    }

    private function getAllowedIps(): array
    {
        $cached = $this->cache->load(self::CACHE_KEY);
        if ($cached) {
            try {
                return $this->json->unserialize($cached);
            } catch (\Throwable) {
            }
        }

        $this->curl->setTimeout(5);
        $this->curl->get(self::API_URL);

        $status = (int) $this->curl->getStatus();
        $body = (string) $this->curl->getBody();

        if ($status < 200 || $status >= 300 || $body === '') {
            throw new \RuntimeException('Invalid response from SimPay IP API');
        }

        $decoded = $this->json->unserialize($body);

        if (!is_array($decoded) || empty($decoded['success']) || empty($decoded['data']) || !is_array($decoded['data'])) {
            throw new \RuntimeException('Invalid JSON structure from SimPay IP API');
        }

        $ips = array_values(array_filter($decoded['data'], 'is_string'));

        $this->cache->save(
            $this->json->serialize($ips),
            self::CACHE_KEY,
            [],
            self::CACHE_TTL
        );

        return $ips;
    }
}