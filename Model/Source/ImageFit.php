<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * How a slide's image fills the banner's frame.
 */
class ImageFit implements OptionSourceInterface
{
    public const COVER = 'cover';
    public const CONTAIN = 'contain';

    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::COVER, 'label' => __('Fill the frame (crop to the aspect ratio)')],
            ['value' => self::CONTAIN, 'label' => __('Show the whole image (no crop)')],
        ];
    }
}
