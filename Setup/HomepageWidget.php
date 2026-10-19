<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Setup;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;
use Magento\Widget\Model\Widget\InstanceFactory;
use Psr\Log\LoggerInterface;
use Zero\Banner\Block\Widget\Banner as BannerWidget;

/**
 * Puts a banner on the home page of a theme (by default Zero's own): a widget
 * instance in "Main Content Top" of cms_index_index. The data patches that
 * create the homepage hero use it, and Zero_DemoStores for a demo store's theme.
 */
class HomepageWidget
{
    private const THEME = 'Zero/default';

    public function __construct(
        private readonly InstanceFactory $widgetInstanceFactory,
        private readonly ThemeCollectionFactory $themeCollectionFactory,
        private readonly State $appState,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param int[] $storeIds store views the widget shows on (0: all)
     */
    public function place(int $bannerId, string $title = 'Homepage hero', string $themePath = self::THEME, array $storeIds = [0]): void
    {
        $theme = $this->themeCollectionFactory->create()
            ->addFieldToFilter('area', Area::AREA_FRONTEND)
            ->addFieldToFilter('theme_path', $themePath)
            ->getFirstItem();
        if (!$theme->getId()) {
            $this->logger->warning('Zero_Banner: theme ' . $themePath . ' not found, homepage hero widget not created');
            return;
        }

        // Frontend: saving resolves the template against the (frontend) theme, which
        // fails under the adminhtml area ("Required parameter 'theme_dir'...").
        $this->appState->emulateAreaCode(Area::AREA_FRONTEND, function () use ($bannerId, $theme, $title, $storeIds) {
            $instance = $this->widgetInstanceFactory->create();
            $instance->setType(BannerWidget::class);
            $instance->setCode('zero_banner');
            $instance->setThemeId((int) $theme->getId());
            $instance->setTitle($title);
            $instance->setStoreIds($storeIds);
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
}
