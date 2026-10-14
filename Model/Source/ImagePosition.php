<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * The part of the image kept when it's cropped to the frame (its focal point).
 */
class ImagePosition implements OptionSourceInterface
{
    public const CENTER = 'center';
    public const POSITIONS = ['center', 'top', 'bottom', 'left', 'right'];

    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'center', 'label' => __('Center')],
            ['value' => 'top', 'label' => __('Top (e.g. keep faces)')],
            ['value' => 'bottom', 'label' => __('Bottom')],
            ['value' => 'left', 'label' => __('Left')],
            ['value' => 'right', 'label' => __('Right')],
        ];
    }
}
