<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Stores → Configuration → Zero → Banners: image output settings shared by all
 * banners (per-banner sizing lives on the banner itself).
 */
class Config
{
    private const XML_PATH_QUALITY = 'zero_banner/images/quality';
    private const XML_PATH_WEBP = 'zero_banner/images/webp';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * JPEG/WebP quality, clamped to 40..95.
     *
     * @return int
     */
    public function getQuality(): int
    {
        $quality = (int) $this->scopeConfig->getValue(self::XML_PATH_QUALITY);

        return $quality > 0 ? max(40, min(95, $quality)) : 80;
    }

    /**
     * Whether WebP copies are generated and offered (when this PHP's GD can write WebP).
     *
     * @return bool
     */
    public function isWebpEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_WEBP) && function_exists('imagewebp');
    }
}
