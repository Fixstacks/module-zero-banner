<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * Backfills store_id 0 ("All Store Views") for banners created before store
 * scoping existed. The frontend filter inner-joins zero_banner_store, so a banner
 * without a row there would silently stop rendering (all existing ones did).
 * New banners always get a store from the form.
 */
class AssignExistingBannersToAllStores implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $bannerTable = $this->moduleDataSetup->getTable('zero_banner');
        $storeTable = $this->moduleDataSetup->getTable('zero_banner_store');

        $select = $connection->select()
            ->from(['banner' => $bannerTable], ['banner_id'])
            ->joinLeft(
                ['store' => $storeTable],
                'banner.banner_id = store.banner_id',
                []
            )
            ->where('store.banner_id IS NULL');

        $unassignedBannerIds = $connection->fetchCol($select);

        if ($unassignedBannerIds) {
            $rows = [];
            foreach ($unassignedBannerIds as $bannerId) {
                $rows[] = ['banner_id' => (int) $bannerId, 'store_id' => Store::DEFAULT_STORE_ID];
            }
            $connection->insertMultiple($storeTable, $rows);
        }
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
