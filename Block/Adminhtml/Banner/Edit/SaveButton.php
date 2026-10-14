<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Block\Adminhtml\Banner\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class SaveButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        return [
            'label' => __('Save Banner'),
            'class' => 'save primary',
            // Without it Magento adds `location.href` to the module's index page, which
            // runs before the save and can take its "saved" message (as Magento_Cms does).
            'on_click' => '',
            'data_attribute' => [
                'mage-init' => [
                    'buttonAdapter' => [
                        'actions' => [
                            [
                                'targetName' => 'zero_banner_form.zero_banner_form',
                                'actionName' => 'save',
                                // Real bug found live: `'back' => false` serializes into the
                                // POST body as the literal STRING "false" — PHP's own
                                // getParam('back') then reads that as a non-empty, truthy
                                // value, so the controller took the "stay on edit" branch
                                // every time regardless of this being false. Omitting the
                                // key entirely (matching stock Magento_Cms's own SaveButton,
                                // which never passes `back: false` either) is what actually
                                // means "go back to the grid" — getParam('back') then
                                // genuinely returns null/falsy.
                                'params' => [true],
                            ],
                        ],
                    ],
                ],
            ],
            'sort_order' => 90,
        ];
    }
}
