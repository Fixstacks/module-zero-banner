<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Model\Source;

use Magento\Store\Ui\Component\Listing\Column\Store\Options as StoreOptionsBase;

/**
 * Website/Store/Store View options with "All Store Views" (0) on top, for the
 * admin form's store multiselect. Same as CMS's own Options class, built on the
 * shared Store Options base so this module doesn't depend on Magento_Cms.
 */
class StoreOptions extends StoreOptionsBase
{
    private const ALL_STORE_VIEWS = '0';

    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $this->currentOptions['All Store Views']['label'] = __('All Store Views');
        $this->currentOptions['All Store Views']['value'] = self::ALL_STORE_VIEWS;

        $this->generateCurrentOptions();

        $this->options = array_values($this->currentOptions);

        return $this->options;
    }
}
