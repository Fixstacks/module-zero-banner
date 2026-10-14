<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Model;

use Magento\Framework\Model\AbstractModel;
use Zero\Banner\Model\ResourceModel\Slide as SlideResource;

/**
 * One slide of a banner. Edited inside the banner form; saving the banner
 * purges the page cache for all its slides.
 *
 * @method int getBannerId()
 * @method int getIsActive()
 * @method int getPosition()
 * @method string|null getImage()
 * @method string|null getMobileImage()
 * @method string|null getAlt()
 * @method string|null getEyebrow()
 * @method string|null getHeading()
 * @method string|null getText()
 * @method string|null getButtonText()
 * @method string|null getLinkUrl()
 * @method string|null getDateFrom()
 * @method string|null getDateTo()
 */
class Slide extends AbstractModel
{
    /**
     * Fields an image upload can go into (also the upload request's param_name).
     */
    public const IMAGE_FIELDS = ['image', 'mobile_image'];

    protected function _construct()
    {
        $this->_init(SlideResource::class);
    }
}
