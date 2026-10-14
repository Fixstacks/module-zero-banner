<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Model\Banner;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Zero\Banner\Model\ResourceModel\Banner\CollectionFactory;
use Zero\Banner\Model\ResourceModel\Slide\CollectionFactory as SlideCollectionFactory;
use Zero\Banner\Model\Slide;

/**
 * Admin form data: the banner plus its slides (for the dynamic rows), with
 * each stored image path turned into the [{name, url}] shape the image
 * uploader field expects. After a failed save, what was posted is shown again.
 */
class DataProvider extends AbstractDataProvider
{
    private ?array $loadedData = null;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly SlideCollectionFactory $slideCollectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly StoreManagerInterface $storeManager,
        private readonly Filesystem $filesystem,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }

        $this->loadedData = [];
        foreach ($this->collection->getItems() as $banner) {
            $data = $banner->getData();
            $data['slides'] = [];
            $slides = $this->slideCollectionFactory->create()->addBannerFilter((int) $banner->getId());
            foreach ($slides as $slide) {
                $slideData = $slide->getData();
                foreach (Slide::IMAGE_FIELDS as $field) {
                    $slideData[$field] = $this->uploaderValue($slideData[$field] ?? null);
                }
                $data['slides'][] = $slideData;
            }
            $this->loadedData[$banner->getId()] = $data;
        }

        $persisted = $this->dataPersistor->get('zero_banner');
        if (!empty($persisted)) {
            $this->loadedData[$persisted['banner_id'] ?? ''] = $persisted;
            $this->dataPersistor->clear('zero_banner');
        }

        return $this->loadedData;
    }

    /**
     * @param string|null $path Media-relative path as stored.
     * @return array<int, array{name: string, url: string, size?: int, type?: string}>
     */
    private function uploaderValue(?string $path): array
    {
        if (!$path) {
            return [];
        }

        $relative = ltrim($path, '/');
        $mediaUrl = rtrim($this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/');
        $value = ['name' => basename($path), 'url' => $mediaUrl . '/' . $relative];

        // Size and type for the uploader's preview caption.
        $media = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        if ($media->isFile($relative)) {
            $value['size'] = (int) ($media->stat($relative)['size'] ?? 0);
            $value['type'] = (string) (mime_content_type($media->getAbsolutePath($relative)) ?: '');
        }

        return [$value];
    }
}
