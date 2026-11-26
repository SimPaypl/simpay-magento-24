<?php

declare(strict_types=1);

namespace SimPay\Magento\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use SimPay\Magento\Model\Config;

/**
 * "Connect with SimPay" (OAuth) is the default for new installations. Shops that already have
 * credentials entered by hand keep the manual connection method in every scope they were saved in.
 */
class SetManualAuthMethodForExistingInstalls implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('core_config_data');

        $rows = $connection->fetchAll(
            $connection->select()
                ->from($table, ['scope', 'scope_id'])
                ->where('path = ?', Config::XML_PATH_API_PASSWORD)
                ->where("value IS NOT NULL AND value <> ''")
        );

        foreach ($rows as $row) {
            $exists = (bool) $connection->fetchOne(
                $connection->select()
                    ->from($table, ['config_id'])
                    ->where('path = ?', Config::XML_PATH_AUTH_METHOD)
                    ->where('scope = ?', $row['scope'])
                    ->where('scope_id = ?', (int) $row['scope_id'])
            );

            if (!$exists) {
                $connection->insert($table, [
                    'scope' => $row['scope'],
                    'scope_id' => (int) $row['scope_id'],
                    'path' => Config::XML_PATH_AUTH_METHOD,
                    'value' => Config::AUTH_METHOD_MANUAL,
                ]);
            }
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
