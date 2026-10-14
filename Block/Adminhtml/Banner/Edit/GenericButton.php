<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Block\Adminhtml\Banner\Edit;

use Magento\Backend\Block\Widget\Context;

/**
 * Base for the edit form's toolbar buttons (as in Magento_Cms).
 */
class GenericButton
{
    public function __construct(
        protected readonly Context $context
    ) {
    }

    public function getBannerId(): ?int
    {
        $id = $this->context->getRequest()->getParam('banner_id');

        return $id ? (int) $id : null;
    }

    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
