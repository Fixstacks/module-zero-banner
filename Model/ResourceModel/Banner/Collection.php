<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Model\ResourceModel\Banner;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Store\Model\Store;
use Zero\Banner\Model\Banner as BannerModel;
use Zero\Banner\Model\ResourceModel\Banner as BannerResource;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'banner_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(BannerModel::class, BannerResource::class);
    }

    /**
     * Restrict to banners assigned to the given store, plus "All Store Views"
     * (0) — same semantics as CMS blocks. group() keeps one row per banner when
     * several of its assignments match.
     *
     * @param int $storeId
     * @return $this
     */
    public function addStoreFilter(int $storeId): self
    {
        $this->getSelect()->join(
            ['store_table' => $this->getTable('zero_banner_entity_store')],
            'main_table.banner_id = store_table.banner_id',
            []
        )->where(
            'store_table.store_id IN (?)',
            [$storeId, Store::DEFAULT_STORE_ID]
        )->group(
            'main_table.banner_id'
        );

        return $this;
    }

    /**
     * Loads each banner's store ids in one query (the admin form's store
     * multiselect).
     *
     * @return $this
     */
    protected function _afterLoad()
    {
        $bannerIds = $this->getColumnValues('banner_id');
        if ($bannerIds) {
            $connection = $this->getConnection();
            $select = $connection->select()
                ->from($this->getTable('zero_banner_entity_store'), ['banner_id', 'store_id'])
                ->where('banner_id IN (?)', $bannerIds);

            $storeIdsByBanner = [];
            foreach ($connection->fetchAll($select) as $row) {
                $storeIdsByBanner[(int) $row['banner_id']][] = (int) $row['store_id'];
            }

            foreach ($this as $item) {
                $item->setData('store_id', $storeIdsByBanner[(int) $item->getId()] ?? []);
            }
        }

        return parent::_afterLoad();
    }
}
