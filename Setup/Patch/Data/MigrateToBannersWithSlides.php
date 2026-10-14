<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Setup\Patch\Data;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;
use Magento\Widget\Model\Widget\InstanceFactory;
use Psr\Log\LoggerInterface;
use Zero\Banner\Block\Widget\Banner as BannerWidget;

/**
 * Banners used to be single slides (zero_banner), all rendered at the top of
 * the homepage by the module's own layout. Now a banner has slides and is
 * placed with a widget, so the old rows become one "Homepage hero" banner and a
 * widget instance puts it where the carousel was (homepage, content.top, Zero
 * theme).
 *
 * Old titles carried hand-written numbering ("01 / 03 New Collection"): it's
 * stripped into the eyebrow, and the banner's "show slide numbers" generates it.
 */
class MigrateToBannersWithSlides implements DataPatchInterface
{
    public const IDENTIFIER = 'homepage-hero';
    private const THEME = 'Zero/default';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly InstanceFactory $widgetInstanceFactory,
        private readonly ThemeCollectionFactory $themeCollectionFactory,
        private readonly State $appState,
        private readonly LoggerInterface $logger
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

        $this->placeOnHomepage($bannerId);
    }

    /**
     * Widget instance: Zero Banner → homepage, "Main Content Top".
     *
     * @param int $bannerId
     * @return void
     */
    private function placeOnHomepage(int $bannerId): void
    {
        $theme = $this->themeCollectionFactory->create()
            ->addFieldToFilter('area', Area::AREA_FRONTEND)
            ->addFieldToFilter('theme_path', self::THEME)
            ->getFirstItem();
        if (!$theme->getId()) {
            $this->logger->warning('Zero_Banner: theme ' . self::THEME . ' not found, homepage hero widget not created');
            return;
        }

        // Frontend: saving resolves the template against the (frontend) theme, which
        // fails under the adminhtml area ("Required parameter 'theme_dir'...").
        $this->appState->emulateAreaCode(Area::AREA_FRONTEND, function () use ($bannerId, $theme) {
            $instance = $this->widgetInstanceFactory->create();
            $instance->setType(BannerWidget::class);
            $instance->setCode('zero_banner');
            $instance->setThemeId((int) $theme->getId());
            $instance->setTitle('Homepage hero');
            $instance->setStoreIds([0]);
            $instance->setSortOrder(0);
            $instance->setWidgetParameters(['banner_id' => (string) $bannerId]);
            $instance->setPageGroups([[
                'page_group' => 'pages',
                'pages' => [
                    'page_id' => 0,
                    'layout_handle' => 'cms_index_index',
                    'block' => 'content.top',
                    'for' => 'all',
                    'template' => 'Zero_Banner::widget/banner.phtml',
                ],
            ]]);
            $instance->save();
        });
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
