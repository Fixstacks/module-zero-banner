<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Model\Source;

/**
 * Mobile aspect ratio: the desktop list plus "same as desktop" (empty).
 */
class MobileAspectRatio extends AspectRatio
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return array_merge([['value' => '', 'label' => __('Same as desktop')]], parent::toOptionArray());
    }
}
