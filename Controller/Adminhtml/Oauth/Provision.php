<?php

declare(strict_types=1);

namespace SimPay\Magento\Controller\Adminhtml\Oauth;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;

/**
 * Service picker submit: creates an API key, sets the IPN URL and IPN key in SimPay and saves them in Magento.
 */
class Provision extends AbstractOauth implements HttpPostActionInterface
{
    public function execute(): ResultInterface
    {
        $scope = $this->oauthService->getRememberedScope();

        try {
            $result = $this->oauthService->getConnector()->connect(
                (string) $this->getRequest()->getParam('service_id', ''),
                $this->oauthService->getIpnUrl($scope),
                $this->oauthService->getApiKeyName($scope)
            );
        } catch (\Throwable $e) {
            return $this->failAndRedirect($e, $scope);
        }

        $this->oauthService->saveConnection($result, $scope);

        $this->messageManager->addSuccessMessage(__(
            'The shop has been connected to SimPay service "%1". API key, IPN URL and IPN signature key were configured automatically.',
            $result->serviceName
        ));

        return $this->redirectToConfig($scope);
    }
}
