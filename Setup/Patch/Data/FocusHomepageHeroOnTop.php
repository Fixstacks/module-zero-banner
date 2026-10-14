<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * The homepage hero's migrated slides are portrait catalog photos (models,
 * head to knee); cropped to 5:2 around the center, the faces were cut off.
 * Their focal point becomes "top". Only untouched slides (still "center").
 */
class FocusHomepageHeroOnTop implements DataPatchInterface
{
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
        $bannerId = (int) $connection->fetchOne(
            $connection->select()
                ->from($this->moduleDataSetup->getTable('zero_banner_entity'), ['banner_id'])
                ->where('identifier = ?', MigrateToBannersWithSlides::IDENTIFIER)
        );
        if (!$bannerId) {
            return;
        }

        $connection->update(
            $this->moduleDataSetup->getTable('zero_banner_slide'),
            ['image_position' => 'top'],
            [
                'banner_id = ?' => $bannerId,
                'image_position = ?' => 'center',
                'image LIKE ?' => '/catalog/product/%',
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [MigrateToBannersWithSlides::class];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
