<?php

declare(strict_types=1);

namespace SimPay\Magento\Controller\Adminhtml\Oauth;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;

/**
 * "Cancel" on the service picker - forgets the temporary token.
 */
class Cancel extends AbstractOauth implements HttpGetActionInterface
{
    public function execute(): ResultInterface
    {
        $scope = $this->oauthService->getRememberedScope();
        $this->oauthService->getConnector()->cancel();

        return $this->redirectToConfig($scope);
    }
}
