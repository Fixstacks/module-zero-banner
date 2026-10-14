<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Zero\Banner\Model\Banner;

class WidthMode implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Banner::WIDTH_CONTAINED, 'label' => __('Content width')],
            ['value' => Banner::WIDTH_FULL, 'label' => __('Full width (edge to edge)')],
        ];
    }
}
