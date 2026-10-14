<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Aspect ratios a banner can use. Images are cropped to it on the server, and
 * the slide gets matching CSS aspect-ratio classes (widget/banner.phtml maps
 * each value to literal Tailwind classes so they get compiled).
 */
class AspectRatio implements OptionSourceInterface
{
    public const RATIOS = [
        '3:1' => '3:1 — slim strip',
        '5:2' => '5:2 — wide hero',
        '2:1' => '2:1',
        '16:9' => '16:9 — video',
        '3:2' => '3:2 — photo',
        '4:3' => '4:3',
        '1:1' => '1:1 — square',
        '4:5' => '4:5 — portrait',
        '2:3' => '2:3',
        '9:16' => '9:16 — story',
    ];

    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::RATIOS as $value => $label) {
            $options[] = ['value' => $value, 'label' => __($label)];
        }

        return $options;
    }

    /**
     * "16:9" → [16, 9]; null for anything not in RATIOS.
     *
     * @param string|null $ratio
     * @return int[]|null
     */
    public static function parse(?string $ratio): ?array
    {
        if ($ratio === null || !isset(self::RATIOS[$ratio])) {
            return null;
        }
        [$w, $h] = explode(':', $ratio);

        return [(int) $w, (int) $h];
    }
}
