<?php

declare(strict_types=1);

namespace SimPay\Magento\Controller\Adminhtml\Oauth;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Psr\Log\LoggerInterface;
use SimPay\Magento\Service\OAuth\OAuthService;

abstract class AbstractOauth extends Action
{
    /** Same ACL as Stores → Configuration → Sales → Payment Methods. */
    public const ADMIN_RESOURCE = 'Magento_Payment::payment';

    public function __construct(
        Context $context,
        protected readonly OAuthService $oauthService,
        protected readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @param array{scope: string, scope_id: int} $scope
     */
    protected function redirectToConfig(array $scope): Redirect
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();

        return $redirect->setPath('adminhtml/system_config/edit', $this->oauthService->getConfigPageParams($scope));
    }

    /**
     * @param array{scope: string, scope_id: int} $scope
     */
    protected function failAndRedirect(\Throwable $e, array $scope): Redirect
    {
        $this->logger->error('[SimPay] OAuth error: ' . $e->getMessage(), ['exception' => $e]);
        $this->messageManager->addErrorMessage($this->oauthService->getErrorMessage($e));

        return $this->redirectToConfig($scope);
    }
}
