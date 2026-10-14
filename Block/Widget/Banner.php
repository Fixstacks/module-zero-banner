<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Block\Widget;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Widget\Block\BlockInterface;
use Zero\Banner\Model\Banner as BannerModel;
use Zero\Banner\Model\ResourceModel\Banner\CollectionFactory as BannerCollectionFactory;
use Zero\Banner\Model\ResourceModel\Slide\CollectionFactory as SlideCollectionFactory;
use Zero\Banner\Model\Slide;
use Zero\Banner\Model\SlidePicture;

/**
 * "Zero Banner" widget: renders one banner's visible slides. Placed through
 * Content → Widgets (any page/container), or directly:
 *   {{widget type="Zero\Banner\Block\Widget\Banner" identifier="homepage-hero"}}
 *   <block class="Zero\Banner\Block\Widget\Banner"><arguments><argument name="identifier" ...
 * Renders nothing if the banner is disabled, not in this store, or has no
 * visible slide.
 */
class Banner extends Template implements BlockInterface, IdentityInterface
{
    /**
     * @var string
     */
    protected $_template = 'Zero_Banner::widget/banner.phtml';

    private BannerModel|false|null $banner = null;

    /**
     * @var Slide[]|null
     */
    private ?array $slides = null;

    /**
     * @param Template\Context $context
     * @param BannerCollectionFactory $bannerCollectionFactory
     * @param SlideCollectionFactory $slideCollectionFactory
     * @param SlidePicture $slidePicture
     * @param StoreManagerInterface $storeManager
     * @param TimezoneInterface $timezone
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        private readonly BannerCollectionFactory $bannerCollectionFactory,
        private readonly SlideCollectionFactory $slideCollectionFactory,
        private readonly SlidePicture $slidePicture,
        private readonly StoreManagerInterface $storeManager,
        private readonly TimezoneInterface $timezone,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * The active banner chosen by `banner_id` (widget setting) or `identifier`,
     * if it's assigned to the current store.
     *
     * @return BannerModel|null
     */
    public function getBanner(): ?BannerModel
    {
        if ($this->banner === null) {
            $collection = $this->bannerCollectionFactory->create()
                ->addFieldToFilter('is_active', 1)
                ->addStoreFilter((int) $this->storeManager->getStore()->getId());
            if ($this->getData('banner_id')) {
                $collection->addFieldToFilter('main_table.banner_id', (int) $this->getData('banner_id'));
            } elseif ($this->getData('identifier')) {
                $collection->addFieldToFilter('identifier', (string) $this->getData('identifier'));
            } else {
                $this->banner = false;
                return null;
            }
            $banner = $collection->setPageSize(1)->getFirstItem();
            $this->banner = $banner->getId() ? $banner : false;
        }

        return $this->banner ?: null;
    }

    /**
     * Enabled slides within their date range today (store timezone), in order,
     * indexed 0..n-1.
     *
     * @return Slide[]
     */
    public function getSlides(): array
    {
        if ($this->slides === null) {
            $banner = $this->getBanner();
            $this->slides = $banner ? array_values(
                $this->slideCollectionFactory->create()
                    ->addBannerFilter((int) $banner->getId())
                    ->addVisibleFilter($this->timezone->date()->format('Y-m-d'))
                    ->getItems()
            ) : [];
        }

        return $this->slides;
    }

    /**
     * @param Slide $slide
     * @return array|null See SlidePicture::build().
     */
    public function getPicture(Slide $slide): ?array
    {
        $banner = $this->getBanner();

        return $banner ? $this->slidePicture->build($banner, $slide) : null;
    }

    /**
     * Page cache tags. Always the list tag, so a page whose banner doesn't show
     * right now still gets purged when that banner is saved.
     *
     * @return string[]
     */
    public function getIdentities(): array
    {
        $identities = [BannerModel::CACHE_TAG];
        $banner = $this->getBanner();
        if ($banner) {
            $identities[] = BannerModel::CACHE_TAG . '_' . $banner->getId();
        }

        return $identities;
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml()
    {
        return $this->getSlides() ? parent::_toHtml() : '';
    }
}
