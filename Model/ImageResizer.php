<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Image\AdapterFactory;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Responsive variants of a banner image: cropped to the banner's aspect ratio
 * on the server around the slide's focal point (so no hidden pixels are
 * downloaded) — or, for "show the whole image", not cropped — scaled to each
 * srcset width, plus a WebP copy when enabled. Stored under
 * media/zero_banner/cache/{16x9 | 16x9-top | original}/{width}q{quality}/{source path}.
 *
 * Works for any media-relative path: uploads (/zero_banner/...) as well as the
 * catalog photos older banners point at (/catalog/product/...).
 */
class ImageResizer
{
    public const CACHE_DIR = 'zero_banner/cache';

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly AdapterFactory $imageAdapterFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Variants for the given widths, smallest first. Widths wider than the
     * cropped source are dropped (never upscaled); the source's own width is
     * then the largest variant. Null when the source file is missing.
     *
     * @param string $imagePath Media-relative path as stored on the slide.
     * @param int[]|null $ratio [width, height] to crop to, e.g. [16, 9]; null keeps the whole image.
     * @param int[] $widths Ascending.
     * @param string $position Focal point kept when cropping (see Source\ImagePosition).
     * @return array<int, array{url: string, webp: string|null, width: int, height: int}>|null
     */
    public function getVariants(string $imagePath, ?array $ratio, array $widths, string $position = 'center'): ?array
    {
        $media = $this->media();
        $source = ltrim($imagePath, '/');
        if ($source === '' || !$media->isFile($source)) {
            return null;
        }

        try {
            $size = getimagesize($media->getAbsolutePath($source));
        } catch (\Throwable $e) {
            $size = false; // not an image (Magento turns the warning into an exception)
        }
        if (!$size || !$size[0] || !$size[1]) {
            return null;
        }
        if ($ratio === null) {
            [$crop, $cropWidth, $cropHeight] = [null, (int) $size[0], (int) $size[1]];
            $ratio = [$cropWidth, $cropHeight];
            $ratioDir = 'original';
        } else {
            [$crop, $cropWidth, $cropHeight] = $this->cropBox((int) $size[0], (int) $size[1], $ratio, $position);
            $ratioDir = $ratio[0] . 'x' . $ratio[1] . ($position === 'center' ? '' : '-' . $position);
        }

        $targets = array_values(array_filter($widths, static fn (int $w) => $w < $cropWidth));
        $targets[] = min($cropWidth, (int) max($widths));

        $quality = $this->config->getQuality();
        $webp = $this->config->isWebpEnabled();

        $variants = [];
        foreach (array_unique($targets) as $width) {
            $height = (int) round($width * $ratio[1] / $ratio[0]);
            $target = self::CACHE_DIR . '/' . $ratioDir . '/' . $width . 'q' . $quality . '/' . $source;
            try {
                if ($this->isStale($media, $target, $source)) {
                    $adapter = $this->imageAdapterFactory->create();
                    $adapter->open($media->getAbsolutePath($source));
                    if ($crop !== null) {
                        $adapter->crop(...$crop);
                    }
                    $adapter->keepAspectRatio(false);
                    $adapter->keepTransparency(true);
                    $adapter->quality($quality);
                    $adapter->resize($width, $height);
                    $adapter->save($media->getAbsolutePath($target));
                }
                $webpPath = $webp ? $this->webp($media, $target, $quality) : null;
            } catch (\Exception $e) {
                $this->logger->error('Zero_Banner: could not resize ' . $source, ['exception' => $e]);

                // The original, uncropped: CSS object-cover still fits it.
                return [[
                    'url' => $this->mediaUrl($source),
                    'webp' => null,
                    'width' => $cropWidth,
                    'height' => $cropHeight,
                ]];
            }

            $variants[] = [
                'url' => $this->mediaUrl($target),
                'webp' => $webpPath !== null ? $this->mediaUrl($webpPath) : null,
                'width' => $width,
                'height' => $height,
            ];
        }

        return $variants;
    }

    /**
     * Drops every resized copy of the image (all ratios, sizes and formats).
     *
     * @param string $imagePath Media-relative path as stored on the slide.
     * @return void
     */
    public function clean(string $imagePath): void
    {
        $media = $this->media();
        $source = ltrim($imagePath, '/');
        if ($source === '' || !$media->isDirectory(self::CACHE_DIR)) {
            return;
        }

        foreach ($media->read(self::CACHE_DIR) as $ratioDir) {
            if (!$media->isDirectory($ratioDir)) {
                continue;
            }
            foreach ($media->read($ratioDir) as $sizeDir) {
                foreach ([$sizeDir . '/' . $source, $sizeDir . '/' . $source . '.webp'] as $copy) {
                    if ($media->isFile($copy)) {
                        $media->delete($copy);
                    }
                }
            }
        }
    }

    /**
     * Crop of a $width×$height image to $ratio around the focal point, as the
     * adapter's crop() arguments (pixels to cut from top, left, right, bottom)
     * plus the resulting size. Only the axis being cut uses the position:
     * "top" on a too-wide image still crops the sides evenly.
     *
     * @param int $width
     * @param int $height
     * @param int[] $ratio
     * @param string $position center, top, bottom, left, right, or a percentage
     *     ("61"): where the kept part sits along the cut axis, as in CSS object-position.
     * @return array{0: int[], 1: int, 2: int}
     */
    public function cropBox(int $width, int $height, array $ratio, string $position = 'center'): array
    {
        $target = $ratio[0] / $ratio[1];
        if ($width / $height > $target) {
            $cropWidth = (int) round($height * $target);
            $cut = $width - $cropWidth;
            $left = match ($position) {
                'left' => 0,
                'right' => $cut,
                default => self::share($cut, $position),
            };

            return [[0, $left, $cut - $left, 0], $cropWidth, $height];
        }

        $cropHeight = (int) round($width / $target);
        $cut = $height - $cropHeight;
        $top = match ($position) {
            'top' => 0,
            'bottom' => $cut,
            default => self::share($cut, $position),
        };

        return [[$top, 0, 0, $cut - $top], $width, $cropHeight];
    }

    /**
     * Pixels cut before the kept part: a percentage position's share of the cut, half otherwise.
     */
    private static function share(int $cut, string $position): int
    {
        return is_numeric($position)
            ? (int) round($cut * max(0.0, min(100.0, (float) $position)) / 100)
            : intdiv($cut, 2);
    }

    private function isStale(WriteInterface $media, string $copy, string $source): bool
    {
        return !$media->isFile($copy) || $media->stat($copy)['mtime'] < $media->stat($source)['mtime'];
    }

    /**
     * WebP copy of an already resized file (GD), next to it as "<file>.webp".
     */
    private function webp(WriteInterface $media, string $resized, int $quality): ?string
    {
        $webp = $resized . '.webp';
        if (!$this->isStale($media, $webp, $resized)) {
            return $webp;
        }

        try {
            $image = imagecreatefromstring($media->readFile($resized));
        } catch (\Throwable $e) {
            $image = false;
        }
        if ($image === false) {
            return null;
        }
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);
        $written = imagewebp($image, $media->getAbsolutePath($webp), $quality);
        imagedestroy($image);

        return $written ? $webp : null;
    }

    private function media(): WriteInterface
    {
        return $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }

    private function mediaUrl(string $path): string
    {
        return rtrim($this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/') . '/' . $path;
    }
}
