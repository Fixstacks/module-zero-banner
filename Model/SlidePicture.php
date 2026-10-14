<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Model;

use Zero\Banner\Model\Source\AspectRatio;
use Zero\Banner\Model\Source\ImageFit;
use Zero\Banner\Model\Source\ImagePosition;

/**
 * Everything the frontend's <picture> needs for one slide: srcset/sizes per
 * format, the exact width/height (so the browser reserves the space — no
 * layout shift), and an optional mobile <source> (its own image, or the
 * desktop one re-cropped to the mobile aspect ratio).
 *
 * Also used on save to generate all variants up front, so the first visitor
 * after an edit doesn't wait for the resizing.
 */
class SlidePicture
{
    /**
     * srcset widths by the banner's width mode. `contained` sits in the page's
     * content column (viewport minus .page-main padding); `full` is edge to edge.
     */
    private const WIDTHS = [
        Banner::WIDTH_CONTAINED => [480, 768, 1024, 1366, 1600, 2048],
        Banner::WIDTH_FULL => [640, 1024, 1366, 1600, 1920, 2560],
    ];
    private const MOBILE_WIDTHS = [360, 540, 720, 1080];

    private const SIZES = [
        Banner::WIDTH_CONTAINED => '(min-width: 48rem) calc(100vw - 4rem), calc(100vw - 2rem)',
        Banner::WIDTH_FULL => '100vw',
    ];
    private const MOBILE_SIZES = [
        Banner::WIDTH_CONTAINED => 'calc(100vw - 2rem)',
        Banner::WIDTH_FULL => '100vw',
    ];

    /**
     * Below Tailwind's md breakpoint (48rem), where the theme switches layouts.
     */
    public const MOBILE_MEDIA = '(max-width: 47.99rem)';

    public function __construct(
        private readonly ImageResizer $imageResizer
    ) {
    }

    /**
     * @param Banner $banner
     * @param Slide $slide
     * @return array{
     *     src: string, srcset: string, webp_srcset: string|null, sizes: string, width: int, height: int,
     *     mobile: array{srcset: string, webp_srcset: string|null, sizes: string, width: int, height: int}|null
     * }|null Null for a text-only slide (or a missing image file).
     */
    public function build(Banner $banner, Slide $slide): ?array
    {
        $image = (string) $slide->getImage();
        $ratio = AspectRatio::parse($banner->getAspectRatio()) ?? [16, 9];
        $mode = isset(self::WIDTHS[$banner->getWidthMode()]) ? $banner->getWidthMode() : Banner::WIDTH_CONTAINED;

        // "Show the whole image": no crop, the frame letterboxes it (CSS object-contain).
        $contain = $slide->getData('image_fit') === ImageFit::CONTAIN;
        $position = in_array($slide->getData('image_position'), ImagePosition::POSITIONS, true)
            ? (string) $slide->getData('image_position')
            : ImagePosition::CENTER;

        $desktop = $image !== ''
            ? $this->imageResizer->getVariants($image, $contain ? null : $ratio, self::WIDTHS[$mode], $position)
            : null;
        if (!$desktop) {
            return null;
        }
        $largest = end($desktop);

        $picture = $this->srcsets($desktop) + [
            'src' => $largest['url'],
            'sizes' => self::SIZES[$mode],
            'width' => $largest['width'],
            'height' => $largest['height'],
            'mobile' => null,
        ];

        $mobileImage = (string) $slide->getMobileImage();
        $mobileRatio = AspectRatio::parse($banner->getAspectRatioMobile());
        if ($mobileImage !== '' || (!$contain && $mobileRatio && $mobileRatio !== $ratio)) {
            $mobile = $this->imageResizer->getVariants(
                $mobileImage !== '' ? $mobileImage : $image,
                $contain ? null : ($mobileRatio ?? $ratio),
                self::MOBILE_WIDTHS,
                $position
            );
            if ($mobile) {
                $largestMobile = end($mobile);
                $picture['mobile'] = $this->srcsets($mobile) + [
                    'sizes' => self::MOBILE_SIZES[$mode],
                    'width' => $largestMobile['width'],
                    'height' => $largestMobile['height'],
                ];
            }
        }

        return $picture;
    }

    /**
     * @param array<int, array{url: string, webp: string|null, width: int, height: int}> $variants
     * @return array{srcset: string, webp_srcset: string|null}
     */
    private function srcsets(array $variants): array
    {
        $srcset = [];
        $webp = [];
        foreach ($variants as $variant) {
            $srcset[] = $variant['url'] . ' ' . $variant['width'] . 'w';
            if ($variant['webp'] !== null) {
                $webp[] = $variant['webp'] . ' ' . $variant['width'] . 'w';
            }
        }

        return [
            'srcset' => implode(', ', $srcset),
            'webp_srcset' => count($webp) === count($srcset) ? implode(', ', $webp) : null,
        ];
    }
}
