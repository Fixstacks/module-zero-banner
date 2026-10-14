<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Cron;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Zero\Banner\Model\BannerFactory;

/**
 * Pages are cached for up to a day, so a slide whose "Show From"/"Show To"
 * date starts or ends today wouldn't appear/disappear until the cache
 * expired. Hourly, this purges the pages of banners with such a slide (a
 * handful of purges per changeover day; nothing on other days).
 */
class PurgeScheduledSlides
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly TimezoneInterface $timezone,
        private readonly BannerFactory $bannerFactory,
        private readonly EventManager $eventManager
    ) {
    }

    public function execute(): void
    {
        $today = $this->timezone->date();
        $yesterday = (clone $today)->modify('-1 day');

        $connection = $this->resource->getConnection();
        $bannerIds = $connection->fetchCol(
            $connection->select()->distinct()
                ->from($this->resource->getTableName('zero_banner_slide'), ['banner_id'])
                ->where('is_active = 1')
                ->where(
                    $connection->quoteInto('date_from = ?', $today->format('Y-m-d'))
                    . ' OR ' . $connection->quoteInto('date_to = ?', $yesterday->format('Y-m-d'))
                )
        );

        foreach ($bannerIds as $bannerId) {
            $banner = $this->bannerFactory->create()->setId((int) $bannerId);
            $banner->cleanModelCache();
            $this->eventManager->dispatch('clean_cache_by_tags', ['object' => $banner]);
        }
    }
}
