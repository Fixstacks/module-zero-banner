<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Controller\Adminhtml\Banner;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Zero\Banner\Model\Banner;
use Zero\Banner\Model\BannerFactory;
use Zero\Banner\Model\ImageUploader;
use Zero\Banner\Model\ResourceModel\Slide\CollectionFactory as SlideCollectionFactory;
use Zero\Banner\Model\Slide;
use Zero\Banner\Model\SlideFactory;
use Zero\Banner\Model\SlidePicture;

/**
 * Saves the banner and its slides in one transaction: posted slides are
 * updated or created, stored ones missing from the post are deleted. Then the
 * page cache is purged and every image variant generated, so the first
 * visitor doesn't pay for the resizing.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Zero_Banner::banner_save';

    private const BANNER_FIELDS = [
        'name', 'identifier', 'is_active', 'store_id', 'aspect_ratio', 'aspect_ratio_mobile', 'width_mode',
        'is_above_fold', 'show_arrows', 'show_dots', 'show_numbers', 'autoplay', 'autoplay_interval',
    ];
    private const SLIDE_FIELDS = [
        'is_active', 'image_fit', 'image_position', 'content_position', 'alt', 'eyebrow', 'heading', 'text',
        'button_text', 'link_url', 'date_from', 'date_to',
    ];

    public function __construct(
        Action\Context $context,
        private readonly BannerFactory $bannerFactory,
        private readonly SlideFactory $slideFactory,
        private readonly SlideCollectionFactory $slideCollectionFactory,
        private readonly ImageUploader $imageUploader,
        private readonly SlidePicture $slidePicture,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if (!$data) {
            return $resultRedirect->setPath('zero_banner/banner/index');
        }

        $id = (int) ($data['banner_id'] ?? 0);
        $banner = $this->bannerFactory->create();
        if ($id) {
            $banner->load($id);
            if (!$banner->getId()) {
                $this->messageManager->addErrorMessage(__('This banner no longer exists.'));

                return $resultRedirect->setPath('zero_banner/banner/index');
            }
        }

        $resource = $banner->getResource();
        $resource->beginTransaction();
        try {
            $banner->addData(array_intersect_key($data, array_flip(self::BANNER_FIELDS)));
            $banner->setData('aspect_ratio_mobile', ($data['aspect_ratio_mobile'] ?? '') ?: null);
            $banner->save();
            $slides = $this->saveSlides($banner, is_array($data['slides'] ?? null) ? $data['slides'] : []);
            $resource->commit();
        } catch (\Exception $e) {
            $resource->rollBack();
            $this->messageManager->addErrorMessage(
                $e instanceof LocalizedException ? $e->getMessage() : __('Something went wrong while saving the banner.')
            );
            if (!$e instanceof LocalizedException) {
                $this->logger->critical($e);
            }
            // Keep what the admin typed: the form's DataProvider restores it.
            $this->dataPersistor->set('zero_banner', $data);

            return $resultRedirect->setPath('zero_banner/banner/edit', ['banner_id' => $id ?: null]);
        }

        // Slides were saved after the banner's own purge: purge again now that
        // everything is committed.
        $banner->cleanModelCache();
        $this->_eventManager->dispatch('clean_cache_by_tags', ['object' => $banner]);
        $this->generateImages($banner, $slides);

        $this->dataPersistor->clear('zero_banner');
        $this->messageManager->addSuccessMessage(__('The banner has been saved.'));

        if ($this->getRequest()->getParam('back')) {
            return $resultRedirect->setPath('zero_banner/banner/edit', ['banner_id' => $banner->getId()]);
        }

        return $resultRedirect->setPath('zero_banner/banner/index');
    }

    /**
     * @param Banner $banner
     * @param array<int|string, mixed> $rows Posted dynamic rows.
     * @return Slide[] The banner's slides after saving.
     * @throws LocalizedException
     */
    private function saveSlides(Banner $banner, array $rows): array
    {
        $existing = [];
        foreach ($this->slideCollectionFactory->create()->addBannerFilter((int) $banner->getId()) as $slide) {
            $existing[(int) $slide->getId()] = $slide;
        }

        $saved = [];
        $position = 0;
        uasort($rows, static fn ($a, $b) => (int) ($a['position'] ?? 0) <=> (int) ($b['position'] ?? 0));
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $slideId = (int) ($row['slide_id'] ?? 0);
            // Only this banner's own slides can be updated through its form.
            $slide = $existing[$slideId] ?? $this->slideFactory->create();
            unset($existing[$slideId]);

            $slide->addData(array_intersect_key($row, array_flip(self::SLIDE_FIELDS)));
            foreach (['date_from', 'date_to'] as $date) {
                $slide->setData($date, ($row[$date] ?? '') ?: null);
            }
            foreach (Slide::IMAGE_FIELDS as $field) {
                $slide->setData($field, $this->resolveImage($row[$field] ?? [], $slide, $field));
            }
            $slide->setData('banner_id', (int) $banner->getId());
            $slide->setData('position', $position++);
            $slide->save();
            $saved[] = $slide;
        }

        foreach ($existing as $removed) {
            $removed->delete();
        }

        return $saved;
    }

    /**
     * The uploader posts [{name, url, ...}] or nothing (removed). A fresh upload
     * (url in the tmp dir) is moved into place; an image picked from the media
     * gallery is used where it is; anything else is the slide's current image
     * posted back and is kept as stored — whatever its path (older slides point
     * at /catalog/product/... photos).
     *
     * @param mixed $value
     * @param Slide $slide
     * @param string $field
     * @return string|null
     * @throws LocalizedException
     */
    private function resolveImage(mixed $value, Slide $slide, string $field): ?string
    {
        $file = is_array($value) ? ($value[0] ?? null) : null;
        if (!is_array($file)) {
            return null;
        }

        if ($this->imageUploader->isTmpUpload($file)) {
            return $this->imageUploader->moveFileFromTmp((string) ($file['name'] ?? ''));
        }

        // Picked with "Select from Gallery": an existing file elsewhere in pub/media.
        $picked = $this->imageUploader->mediaPathFromUrl((string) ($file['url'] ?? ''));
        if ($picked !== null && $picked !== $slide->getData($field)) {
            return $picked;
        }

        return $slide->getId() ? $slide->getData($field) : null;
    }

    /**
     * @param Banner $banner
     * @param Slide[] $slides
     * @return void
     */
    private function generateImages(Banner $banner, array $slides): void
    {
        foreach ($slides as $slide) {
            try {
                $this->slidePicture->build($banner, $slide);
            } catch (\Exception $e) {
                $this->logger->warning('Zero_Banner: could not pre-generate images', ['exception' => $e]);
            }
        }
    }
}
