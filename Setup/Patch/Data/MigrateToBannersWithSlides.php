<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Zero\Banner\Setup\HomepageWidget;

/**
 * Banners used to be single slides (zero_banner) placed by the module's
 * layout. Now the old rows become one "Homepage hero" banner with slides, and
 * a widget puts it where the carousel was (Setup\HomepageWidget).
 * Hand-written numbering in old titles ("01 / 03 ...") moves into the eyebrow.
 */
class MigrateToBannersWithSlides implements DataPatchInterface
{
    public const IDENTIFIER = 'homepage-hero';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly HomepageWidget $homepageWidget
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $oldSlides = $connection->fetchAll(
            $connection->select()
                ->from($this->moduleDataSetup->getTable('zero_banner'))
                ->order(['sort_order ASC', 'banner_id ASC'])
        );
        if (!$oldSlides || $connection->fetchOne(
            $connection->select()->from($this->moduleDataSetup->getTable('zero_banner_entity'), ['COUNT(*)'])
        )) {
            return;
        }

        $connection->insert($this->moduleDataSetup->getTable('zero_banner_entity'), [
            'name' => 'Homepage hero',
            'identifier' => self::IDENTIFIER,
            'is_active' => 1,
            'aspect_ratio' => '5:2',
            'aspect_ratio_mobile' => '4:5',
            'width_mode' => 'contained',
            'is_above_fold' => 1,
            'show_arrows' => 1,
            'show_dots' => 1,
            'show_numbers' => 1,
            'autoplay' => 0,
            'autoplay_interval' => 6,
        ]);
        $bannerId = (int) $connection->lastInsertId($this->moduleDataSetup->getTable('zero_banner_entity'));

        $storeIds = $connection->fetchCol(
            $connection->select()->distinct()->from($this->moduleDataSetup->getTable('zero_banner_store'), ['store_id'])
        ) ?: [0];
        $connection->insertMultiple(
            $this->moduleDataSetup->getTable('zero_banner_entity_store'),
            array_map(static fn ($storeId) => ['banner_id' => $bannerId, 'store_id' => (int) $storeId], $storeIds)
        );

        $rows = [];
        foreach ($oldSlides as $position => $old) {
            $eyebrow = trim((string) preg_replace('#^\s*\d+\s*/\s*\d+\s*#', '', (string) $old['title']));
            $rows[] = [
                'banner_id' => $bannerId,
                'is_active' => (int) $old['is_active'] ? 1 : 0,
                'position' => $position,
                'image' => $old['image'] ?: null,
                'alt' => $eyebrow ?: ($old['subtitle'] ?: null),
                'eyebrow' => $eyebrow ?: null,
                'heading' => $old['subtitle'] ?: null,
                'button_text' => $old['button_text'] ?: null,
                'link_url' => $old['link_url'] ?: null,
            ];
        }
        $connection->insertMultiple($this->moduleDataSetup->getTable('zero_banner_slide'), $rows);

        $this->homepageWidget->place($bannerId);
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [AssignExistingBannersToAllStores::class];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
