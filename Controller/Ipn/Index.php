<?php

declare(strict_types=1);

namespace SimPay\Magento\Controller\Ipn;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use SimPay\Magento\Model\Config;
use SimPay\Magento\Service\IpAllowlistService;
use SimPay\Magento\Service\Ipn\IpnDispatcher;
use SimPay\Magento\Service\Ipn\IpnValidator;

class Index extends Action implements CsrfAwareActionInterface
{
    public function __construct(
        Context $context,
        private readonly RawFactory $resultRawFactory,
        private readonly Json $json,
        private readonly LoggerInterface $logger,
        private readonly Config $config,
        private readonly IpnValidator $validator,
        private readonly IpnDispatcher $dispatcher,
        private readonly IpAllowlistService $ipAllowlistService
    ) {
        parent::__construct($context);
    }

    public function execute(): Raw
    {
        $result = $this->resultRawFactory->create();

        try {
            // Basic config presence check
            $serviceId = (string)$this->config->getServiceId();
            $ipnKey = (string)$this->config->getIpnSignatureKey();

            if ($serviceId === '' || $ipnKey === '') {
                return $this->badRequest($result, 'Missing API configuration (service_id / ipn key).');
            }

            // Optional: validate IP allowlist
            if ($this->config->isIpnCheckIpEnabled()) {
                $ip = (string)($this->getRequest()->getClientIp() ?? '');
                if ($ip === '' || !$this->ipAllowlistService->isAllowed($ip)) {
                    return $this->badRequest($result, 'Invalid IP address: ' . $ip);
                }
            }

            // Raw JSON body
            $rawBody = (string)$this->getRequest()->getContent();
            if ($rawBody === '') {
                return $this->badRequest($result, 'Cannot read payload (empty body).');
            }

            $payload = $this->json->unserialize($rawBody);
            if (!is_array($payload) || $payload === []) {
                return $this->badRequest($result, 'Invalid JSON payload.');
            }

            // Validate signature, required fields, service_id etc.
            $this->validator->validate($payload, $serviceId, $ipnKey);

            // Dispatch event
            $this->dispatcher->dispatch($payload);

            $result->setHttpResponseCode(200);
            $result->setContents('OK');
            return $result;

        } catch (\Throwable $e) {
            $this->logger->error('SimPay IPN error', [
                'message' => $e->getMessage(),
            ]);

            return $this->badRequest($result, $e->getMessage());
        }
    }

    private function badRequest(Raw $result, string $message): Raw
    {
        $result->setHttpResponseCode(400);
        $result->setContents($message);
        return $result;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true; // do NOT validate CSRF
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null; // no exception
    }
}