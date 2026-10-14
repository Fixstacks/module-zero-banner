<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Model\ResourceModel;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Store\Model\Store;
use Zero\Banner\Model\ImageUploader;

/**
 * Banner resource model. _afterSave() writes the store assignments to
 * zero_banner_entity_store — same store_id array shape (0 = All Store Views)
 * as cms_block_store, which is what the admin form posts. Deleting a banner
 * deletes its slides (FK cascade) and, once committed, their uploaded images.
 */
class Banner extends AbstractDb
{
    /**
     * @param Context $context
     * @param ImageUploader $imageUploader
     * @param string|null $connectionName
     */
    public function __construct(
        Context $context,
        private readonly ImageUploader $imageUploader,
        $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('zero_banner_entity', 'banner_id');
    }

    /**
     * The identifier is what CMS content and layout XML refer to: lowercase
     * letters, digits, - and _, unique.
     *
     * @inheritDoc
     */
    protected function _beforeSave(AbstractModel $object)
    {
        $identifier = trim((string) $object->getData('identifier'));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $identifier)) {
            throw new LocalizedException(
                __('The identifier may contain only lowercase letters (a-z), digits, "-" and "_".')
            );
        }
        $object->setData('identifier', $identifier);

        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['banner_id'])
            ->where('identifier = ?', $identifier);
        if ($object->getId()) {
            $select->where('banner_id != ?', (int) $object->getId());
        }
        if ($connection->fetchOne($select)) {
            throw new LocalizedException(__('A banner with the identifier "%1" already exists.', $identifier));
        }

        return parent::_beforeSave($object);
    }

    /**
     * @inheritDoc
     */
    protected function _afterSave(AbstractModel $object)
    {
        $storeIds = $object->getData('store_id');
        if ($storeIds !== null) {
            $this->saveStoreRelation($object, (array) $storeIds);
        }

        return parent::_afterSave($object);
    }

    /**
     * @inheritDoc
     */
    protected function _beforeDelete(AbstractModel $object)
    {
        $connection = $this->getConnection();
        $images = $connection->fetchAll(
            $connection->select()
                ->from($this->getTable('zero_banner_slide'), ['image', 'mobile_image'])
                ->where('banner_id = ?', (int) $object->getId())
        );
        $this->addCommitCallback(function () use ($images) {
            foreach ($images as $row) {
                $this->imageUploader->deleteImage($row['image']);
                $this->imageUploader->deleteImage($row['mobile_image']);
            }
        });

        return parent::_beforeDelete($object);
    }

    /**
     * Replace the banner's store assignments with the posted ones.
     *
     * @param AbstractModel $object
     * @param int[] $storeIds
     * @return void
     */
    private function saveStoreRelation(AbstractModel $object, array $storeIds): void
    {
        $connection = $this->getConnection();
        $bannerId = (int) $object->getId();
        $table = $this->getTable('zero_banner_entity_store');

        $connection->delete($table, ['banner_id = ?' => $bannerId]);

        $storeIds = array_unique(array_map('intval', $storeIds));
        if (!$storeIds) {
            $storeIds = [Store::DEFAULT_STORE_ID];
        }

        $rows = [];
        foreach ($storeIds as $storeId) {
            $rows[] = ['banner_id' => $bannerId, 'store_id' => $storeId];
        }

        $connection->insertMultiple($table, $rows);
    }
}
