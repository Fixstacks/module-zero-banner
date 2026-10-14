<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Zero\Banner\Model\ResourceModel\Banner\CollectionFactory;

/**
 * Banners to choose from in the widget's settings.
 */
class Banners implements OptionSourceInterface
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->collectionFactory->create()->setOrder('name', 'ASC') as $banner) {
            $options[] = [
                'value' => (int) $banner->getId(),
                'label' => $banner->getName() . ' (' . $banner->getIdentifier() . ')',
            ];
        }

        return $options;
    }
}
