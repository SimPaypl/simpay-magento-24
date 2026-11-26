<?php

declare(strict_types=1);

namespace SimPay\Magento\Model;

use Magento\Framework\App\CacheInterface as MagentoCache;
use Magento\Framework\Serialize\Serializer\Json;
use SimPay\SDK\CacheInterface;

/**
 * Magento cache for the SDK (used for the SimPay IP allowlist).
 */
class SimPayCache implements CacheInterface
{
    public function __construct(
        private readonly MagentoCache $cache,
        private readonly Json $json
    ) {
    }

    public function get(string $key): mixed
    {
        $value = $this->cache->load($key);

        return $value === false ? null : $this->json->unserialize($value);
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        $this->cache->save($this->json->serialize($value), $key, [], $ttl);
    }

    public function delete(string $key): void
    {
        $this->cache->remove($key);
    }
}
