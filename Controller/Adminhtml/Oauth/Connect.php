<?php

declare(strict_types=1);

namespace SimPay\Magento\Controller\Adminhtml\Oauth;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;

/**
 * "Connect with SimPay" button: starts the OAuth flow and redirects the browser to SimPay.
 */
class Connect extends AbstractOauth implements HttpGetActionInterface
{
    public function execute(): ResultInterface
    {
        $request = $this->getRequest();
        $scope = $this->oauthService->resolveScope($request->getParam('website'), $request->getParam('store'));

        try {
            $authorizationUrl = $this->oauthService->getConnector()->start($this->oauthService->getRedirectUri());
        } catch (\Throwable $e) {
            return $this->failAndRedirect($e, $scope);
        }

        $this->oauthService->rememberScope($scope);

        return $this->resultRedirectFactory->create()->setUrl($authorizationUrl);
    }
}
