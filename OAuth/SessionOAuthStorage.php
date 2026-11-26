<?php

declare(strict_types=1);

namespace SimPay\Magento\OAuth;

use Magento\Backend\Model\Session;
use SimPay\SDK\OAuth\OAuthStorageInterface;

/**
 * Keeps the SimPay OAuth flow (PKCE verifier, state, temporary access token) in the admin user's session.
 */
class SessionOAuthStorage implements OAuthStorageInterface
{
    public function __construct(
        private readonly Session $session
    ) {
    }

    public function get(string $key): ?array
    {
        $value = $this->session->getData($key);

        return is_array($value) ? $value : null;
    }

    public function set(string $key, array $value): void
    {
        $this->session->setData($key, $value);
    }

    public function remove(string $key): void
    {
        $this->session->unsetData($key);
    }
}
