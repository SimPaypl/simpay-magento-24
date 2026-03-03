<?php

declare(strict_types=1);

namespace SimPay\Magento\Gateway\Http;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Psr\Log\LoggerInterface;
use SimPay\Magento\Model\Config;

class Client implements ClientInterface
{
    public function __construct(
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly LoggerInterface $logger,
        private readonly Config $config
    ) {
    }

    public function placeRequest(TransferInterface $transferObject): array
    {
        // SimPay API request
        $this->logger->alert('SimPay request start', [

        ]);

        $bearerToken = (string) $this->config->getBearerToken();
        if ($bearerToken === '') {
            throw new \RuntimeException('SimPay is not configured (missing API password / bearer token).');
        }

        $uri = (string) $transferObject->getUri();
        if ($uri === '') {
            throw new \RuntimeException('SimPay transfer URI is empty (TransferFactory misconfigured).');
        }

        $method = strtoupper((string) ($transferObject->getMethod() ?? 'POST'));

        $body = $transferObject->getBody();
        if (!is_array($body)) {
            $body = [];
        }

        $headers = $transferObject->getHeaders();
        if (!is_array($headers)) {
            $headers = [];
        }

        // TransferFactory sets Accept/Content-Type etc.; Client adds auth
        $headers = array_merge(
            $headers,
            [
                'Authorization' => 'Bearer ' . $bearerToken,
            ]
        );

        $timeout = (int) ($transferObject->getClientConfig()['timeout'] ?? 30);

        try {
            foreach ($headers as $name => $value) {
                $this->curl->addHeader((string) $name, (string) $value);
            }

            $this->curl->setTimeout($timeout);

            $payload = $this->json->serialize($body);

            // MVP: we mainly need POST. Add GET support for future.
            if ($method === 'GET') {
                $this->curl->get($uri);
            } else {
                // Curl::post expects string|array; we send JSON string
                $this->curl->post($uri, $payload);
            }

            $this->logger->alert('SimPay request payload', [
                'method' => $method,
                'uri' => $uri,
                'payload' => $payload,
            ]);

            $status = (int) $this->curl->getStatus();
            $raw = (string) $this->curl->getBody();

            $decoded = [];
            if ($raw !== '') {
                try {
                    $decoded = $this->json->unserialize($raw);
                } catch (\Throwable $e) {
                    $decoded = ['raw' => $raw];
                }
            }

            if ($status >= 200 && $status < 300) {
                return is_array($decoded) ? $decoded : ['data' => $decoded];
            }

            $this->logger->error('SimPay API error', [
                'status' => $status,
                'method' => $method,
                'uri' => $uri,
                'response' => $decoded,
            ]);

            throw new \RuntimeException(sprintf(
                'SimPay API error: HTTP %d',
                $status
            ));
        } catch (\Throwable $e) {
            $this->logger->critical('SimPay request failed', [
                'method' => $method,
                'uri' => $uri,
                'exception' => $e->getMessage(),
            ]);

            throw new \RuntimeException('SimPay request failed: ' . $e->getMessage(), 0, $e);
        }
    }
}