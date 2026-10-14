<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Zero\Banner\Model\ImageUploader;
use Zero\Banner\Model\Slide as SlideModel;

/**
 * Slide resource model. A replaced or removed image, or a deleted slide's
 * images, are deleted once the change commits (uploads only, see
 * ImageUploader::deleteImage()).
 */
class Slide extends AbstractDb
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
        $this->_init('zero_banner_slide', 'slide_id');
    }

    /**
     * @inheritDoc
     */
    protected function _afterSave(AbstractModel $object)
    {
        foreach (SlideModel::IMAGE_FIELDS as $field) {
            $old = $object->getOrigData($field);
            if ($old && $old !== $object->getData($field)) {
                $this->addCommitCallback(fn () => $this->imageUploader->deleteImage($old));
            }
        }

        return parent::_afterSave($object);
    }

    /**
     * @inheritDoc
     */
    protected function _afterDelete(AbstractModel $object)
    {
        foreach (SlideModel::IMAGE_FIELDS as $field) {
            $image = $object->getData($field);
            if ($image) {
                $this->addCommitCallback(fn () => $this->imageUploader->deleteImage($image));
            }
        }

        return parent::_afterDelete($object);
    }
}
