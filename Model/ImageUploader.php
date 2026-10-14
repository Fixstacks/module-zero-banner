<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Uploads the banner image to media/zero_banner/tmp and moves it into place on
 * save — the standard "one image field" admin flow.
 */
class ImageUploader
{
    private const UPLOAD_DIR = 'zero_banner';

    /**
     * Uploads abandoned in tmp (form never saved) are removed after this long.
     */
    private const TMP_TTL = 86400;

    public function __construct(
        private readonly UploaderFactory $uploaderFactory,
        private readonly Filesystem $filesystem,
        private readonly LoggerInterface $logger,
        private readonly StoreManagerInterface $storeManager,
        private readonly ImageResizer $imageResizer
    ) {
    }

    /**
     * @param string $fileId The $_FILES key the form submitted this file under.
     * @return array<string, mixed> Uploader result (file, name, size, url), without filesystem paths.
     * @throws LocalizedException
     */
    public function saveFileToTmpDir(string $fileId): array
    {
        $this->purgeStaleTmpFiles();

        $baseTmpPath = $this->getBaseTmpPath();
        $uploader = $this->uploaderFactory->create(['fileId' => $fileId]);
        // No SVG (it can carry <script> that runs on the store's origin from pub/media),
        // no WebP/AVIF (Magento's GD adapter can't crop/resize them; WebP is generated).
        $uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png']);
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);

        $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $result = $uploader->save($mediaDirectory->getAbsolutePath($baseTmpPath));

        unset($result['tmp_name'], $result['path']);
        $result['url'] = $this->getBaseTmpUrl() . '/' . $result['file'];

        return $result;
    }

    /**
     * Whether the image field's value is a fresh upload still in the tmp dir (as
     * opposed to the banner's current image, which the form posts back unchanged).
     *
     * @param array<string, mixed> $file One entry of the posted `image` field.
     * @return bool
     */
    public function isTmpUpload(array $file): bool
    {
        $url = (string) ($file['url'] ?? '');

        return $url !== '' && str_starts_with($url, $this->getBaseTmpUrl() . '/');
    }

    /**
     * Media-relative path ("/wysiwyg/x.jpg") of an existing image picked with
     * the uploader's "Select from Gallery", from its URL; null unless it's an
     * image file inside pub/media (not a tmp upload or a generated copy).
     *
     * @param string $url
     * @return string|null
     */
    public function mediaPathFromUrl(string $url): ?string
    {
        $mediaUrl = rtrim($this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/') . '/';
        $path = parse_url($url, PHP_URL_PATH);
        $mediaPath = parse_url($mediaUrl, PHP_URL_PATH);
        if (!is_string($path) || !is_string($mediaPath) || !str_starts_with($path, $mediaPath)) {
            return null;
        }

        $relative = rawurldecode(substr($path, strlen($mediaPath)));
        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        if (str_contains($relative, '..')
            || str_starts_with($relative, $this->getBaseTmpPath() . '/')
            || str_starts_with($relative, ImageResizer::CACHE_DIR . '/')
            || !in_array($extension, ['jpg', 'jpeg', 'png', 'gif'], true)
            || !$this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->isFile($relative)
        ) {
            return null;
        }

        return '/' . $relative;
    }

    /**
     * Moves an uploaded image from the tmp dir into its permanent location,
     * renaming it if that name is taken (another banner's image must not be
     * overwritten).
     *
     * @param string $imageName
     * @return string The permanent, relative-to-media path to store on the entity.
     * @throws LocalizedException When the uploaded file is gone or can't be moved.
     */
    public function moveFileFromTmp(string $imageName): string
    {
        // The name comes from the admin POST: strip directories so `../` can't move
        // other files inside pub/media.
        $imageName = basename($imageName);
        $tmpFile = $this->getBaseTmpPath() . '/' . $imageName;

        $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        if ($imageName === '' || !$mediaDirectory->isFile($tmpFile)) {
            throw new LocalizedException(__('The uploaded image was not found. Please upload it again.'));
        }

        $finalName = $imageName;
        $info = pathinfo($imageName);
        $suffix = isset($info['extension']) ? '.' . $info['extension'] : '';
        for ($i = 1; $mediaDirectory->isExist($this->getBasePath() . '/' . $finalName); $i++) {
            $finalName = $info['filename'] . '_' . $i . $suffix;
        }

        try {
            $mediaDirectory->renameFile($tmpFile, $this->getBasePath() . '/' . $finalName);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            throw new LocalizedException(__('The image could not be saved. Please try again.'), $e);
        }

        return '/' . self::UPLOAD_DIR . '/' . $finalName;
    }

    /**
     * Deletes a banner image file and its resized copies — only for uploads
     * (zero_banner/), never the catalog photos some banners point at.
     *
     * @param string|null $imagePath Media-relative path as stored on the banner.
     * @return void
     */
    public function deleteImage(?string $imagePath): void
    {
        $path = ltrim((string) $imagePath, '/');
        if (!str_starts_with($path, self::UPLOAD_DIR . '/') || str_contains($path, '..')
            || $path !== $this->getBasePath() . '/' . basename($path)
        ) {
            return;
        }

        try {
            $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            if ($mediaDirectory->isFile($path)) {
                $mediaDirectory->delete($path);
            }
            $this->imageResizer->clean($path);
        } catch (\Exception $e) {
            $this->logger->warning('Zero_Banner: could not delete ' . $path, ['exception' => $e]);
        }
    }

    public function getBaseTmpPath(): string
    {
        return self::UPLOAD_DIR . '/tmp';
    }

    public function getBasePath(): string
    {
        return self::UPLOAD_DIR;
    }

    /**
     * Removes tmp uploads older than TMP_TTL (forms that were never saved).
     *
     * @return void
     */
    private function purgeStaleTmpFiles(): void
    {
        try {
            $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $tmpPath = $this->getBaseTmpPath();
            if (!$mediaDirectory->isDirectory($tmpPath)) {
                return;
            }
            $cutoff = time() - self::TMP_TTL;
            foreach ($mediaDirectory->read($tmpPath) as $file) {
                if ($mediaDirectory->isFile($file) && $mediaDirectory->stat($file)['mtime'] < $cutoff) {
                    $mediaDirectory->delete($file);
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning('Zero_Banner: could not purge tmp uploads', ['exception' => $e]);
        }
    }

    public function getBaseTmpUrl(): string
    {
        // A URL, not a filesystem path (that would leak the install path in the upload JSON).
        return rtrim($this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/')
            . '/' . $this->getBaseTmpPath();
    }
}
