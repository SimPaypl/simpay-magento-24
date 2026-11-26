<?php

declare(strict_types=1);

namespace SimPay\Magento\Controller\Adminhtml\Oauth;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultInterface;

/**
 * Redirect target after SimPay authorization. SimPay cannot know the admin secret URL key,
 * so the key check is skipped here - the request is bound to the flow by the OAuth state + PKCE
 * stored in the logged-in admin's session.
 */
class Callback extends AbstractOauth implements HttpGetActionInterface, CsrfAwareActionInterface
{
    /** @var string[] */
    protected $_publicActions = ['callback'];

    public function execute(): ResultInterface
    {
        $scope = $this->oauthService->getRememberedScope();

        try {
            $this->oauthService->getConnector()->handleCallback((array) $this->getRequest()->getQueryValue());
        } catch (\Throwable $e) {
            return $this->failAndRedirect($e, $scope);
        }

        // service picker is rendered on the configuration page from the pending connection
        return $this->redirectToConfig($scope);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
