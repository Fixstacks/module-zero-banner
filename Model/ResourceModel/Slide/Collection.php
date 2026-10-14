<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Model\ResourceModel\Slide;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Zero\Banner\Model\ResourceModel\Slide as SlideResource;
use Zero\Banner\Model\Slide as SlideModel;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'slide_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(SlideModel::class, SlideResource::class);
    }

    /**
     * The banner's slides in display order.
     *
     * @param int $bannerId
     * @return $this
     */
    public function addBannerFilter(int $bannerId): self
    {
        $this->addFieldToFilter('banner_id', $bannerId);
        $this->setOrder('position', self::SORT_ORDER_ASC);
        $this->addOrder('slide_id', self::SORT_ORDER_ASC);

        return $this;
    }

    /**
     * Enabled slides whose date range (inclusive, either end optional) covers $date.
     *
     * @param string $date Y-m-d in the store's timezone.
     * @return $this
     */
    public function addVisibleFilter(string $date): self
    {
        $this->addFieldToFilter('is_active', 1);
        $this->getSelect()
            ->where('main_table.date_from IS NULL OR main_table.date_from <= ?', $date)
            ->where('main_table.date_to IS NULL OR main_table.date_to >= ?', $date);

        return $this;
    }
}
