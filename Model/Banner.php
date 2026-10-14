<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Zero\Banner\Model\ResourceModel\Banner as BannerResource;

/**
 * A banner: display settings plus an ordered set of slides (Model\Slide).
 * Placed on pages with the "Zero Banner" widget.
 *
 * @method string getName()
 * @method string getIdentifier()
 * @method int getIsActive()
 * @method string getAspectRatio()
 * @method string|null getAspectRatioMobile()
 * @method string getWidthMode()
 * @method int getIsAboveFold()
 * @method int getShowArrows()
 * @method int getShowDots()
 * @method int getShowNumbers()
 * @method int getAutoplay()
 * @method int getAutoplayInterval()
 */
class Banner extends AbstractModel implements IdentityInterface
{
    /**
     * Page cache tag; Block\Widget\Banner tags its pages with it.
     */
    public const CACHE_TAG = 'zero_banner';

    public const WIDTH_CONTAINED = 'contained';
    public const WIDTH_FULL = 'full';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * @var string
     */
    protected $_eventPrefix = 'zero_banner';

    /**
     * Saving or deleting a banner purges FPC/Varnish by these tags. The list tag
     * is included: a page whose widget points at a banner that didn't render
     * (inactive, no slides) only carries that one.
     *
     * @return string[]
     */
    public function getIdentities(): array
    {
        $identities = [self::CACHE_TAG];
        if ($this->getId()) {
            $identities[] = self::CACHE_TAG . '_' . $this->getId();
        }

        return $identities;
    }

    protected function _construct()
    {
        $this->_init(BannerResource::class);
    }
}
