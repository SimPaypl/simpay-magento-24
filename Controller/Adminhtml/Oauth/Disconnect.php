<?php

declare(strict_types=1);

namespace SimPay\Magento\Controller\Adminhtml\Oauth;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;

/**
 * Removes OAuth-provisioned credentials from the configuration scope the button was clicked in.
 */
class Disconnect extends AbstractOauth implements HttpPostActionInterface
{
    public function execute(): ResultInterface
    {
        $request = $this->getRequest();
        $scope = $this->oauthService->resolveScope($request->getParam('website'), $request->getParam('store'));

        $this->oauthService->getConnector()->cancel();
        $this->oauthService->clearConnection($scope);

        $this->messageManager->addSuccessMessage(__(
            'The shop has been disconnected from SimPay. SimPay payments will not work until you connect again or enter the credentials manually.'
        ));

        return $this->redirectToConfig($scope);
    }
}
