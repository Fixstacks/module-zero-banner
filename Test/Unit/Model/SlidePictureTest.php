<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Test\Unit\Model;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zero\Banner\Model\Banner;
use Zero\Banner\Model\ImageResizer;
use Zero\Banner\Model\Slide;
use Zero\Banner\Model\SlidePicture;

#[AllowMockObjectsWithoutExpectations]
class SlidePictureTest extends TestCase
{
    private ImageResizer&MockObject $resizer;

    /**
     * @var array<int, array{0: string, 1: int[]}> getVariants() calls: [path, ratio]
     */
    private array $calls = [];

    protected function setUp(): void
    {
        $this->resizer = $this->createMock(ImageResizer::class);
        $this->resizer->method('getVariants')->willReturnCallback(function ($path, $ratio, $widths) {
            $this->calls[] = [$path, $ratio];
            return array_map(static fn ($w) => [
                'url' => "https://m/$path/$w.jpg",
                'webp' => "https://m/$path/$w.jpg.webp",
                'width' => $w,
                'height' => (int) round($w * $ratio[1] / $ratio[0]),
            ], array_slice($widths, 0, 2));
        });
    }

    public function testDesktopPictureHasSrcsetsAndTheLargestSize(): void
    {
        $picture = (new SlidePicture($this->resizer))->build(
            $this->banner(['aspect_ratio' => '5:2', 'width_mode' => 'contained']),
            $this->slide(['image' => '/zero_banner/a.jpg'])
        );

        $this->assertSame('https://m//zero_banner/a.jpg/768.jpg', $picture['src']);
        $this->assertSame(
            'https://m//zero_banner/a.jpg/480.jpg 480w, https://m//zero_banner/a.jpg/768.jpg 768w',
            $picture['srcset']
        );
        $this->assertStringEndsWith('768.jpg.webp 768w', $picture['webp_srcset']);
        $this->assertSame([768, 307], [$picture['width'], $picture['height']]);
        $this->assertStringContainsString('calc(100vw - 4rem)', $picture['sizes']);
        $this->assertNull($picture['mobile'], 'same ratio on phones and no mobile image');
    }

    public function testDifferentPhoneRatioRecropsTheSameImage(): void
    {
        $picture = (new SlidePicture($this->resizer))->build(
            $this->banner(['aspect_ratio' => '5:2', 'aspect_ratio_mobile' => '4:5', 'width_mode' => 'full']),
            $this->slide(['image' => '/zero_banner/a.jpg'])
        );

        $this->assertSame([['/zero_banner/a.jpg', [5, 2]], ['/zero_banner/a.jpg', [4, 5]]], $this->calls);
        $this->assertSame('100vw', $picture['mobile']['sizes']);
        $this->assertSame([540, 675], [$picture['mobile']['width'], $picture['mobile']['height']]);
    }

    public function testMobileImageIsUsedOnPhones(): void
    {
        (new SlidePicture($this->resizer))->build(
            $this->banner(['aspect_ratio' => '16:9']),
            $this->slide(['image' => '/zero_banner/a.jpg', 'mobile_image' => '/zero_banner/m.jpg'])
        );

        $this->assertSame(['/zero_banner/m.jpg', [16, 9]], $this->calls[1]);
    }

    public function testFocalPointAndWholeImageArePassedOn(): void
    {
        $positions = [];
        $this->resizer = $this->createMock(ImageResizer::class);
        $this->resizer->method('getVariants')->willReturnCallback(
            function ($path, $ratio, $widths, $position) use (&$positions) {
                $positions[] = [$ratio, $position];
                return [['url' => 'u', 'webp' => null, 'width' => 100, 'height' => 50]];
            }
        );
        $picture = new SlidePicture($this->resizer);
        $banner = $this->banner(['aspect_ratio' => '5:2', 'aspect_ratio_mobile' => '4:5']);

        $picture->build($banner, $this->slide(['image' => '/a.jpg', 'image_position' => 'top']));
        $this->assertSame([[[5, 2], 'top'], [[4, 5], 'top']], $positions);

        $positions = [];
        $whole = $picture->build($banner, $this->slide(['image' => '/a.jpg', 'image_fit' => 'contain']));
        $this->assertSame([[null, 'center']], $positions, 'no crop, and no separate phone crop');
        $this->assertNull($whole['mobile']);

        $positions = [];
        $picture->build($banner, $this->slide(['image' => '/a.jpg', 'image_position' => 'diagonal']));
        $this->assertSame('center', $positions[0][1], 'unknown values fall back to center');
    }

    public function testTextOnlySlideHasNoPicture(): void
    {
        $this->assertNull((new SlidePicture($this->resizer))->build($this->banner([]), $this->slide([])));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function banner(array $data): Banner
    {
        $banner = $this->createPartialMock(Banner::class, []);
        $banner->setData($data);

        return $banner;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function slide(array $data): Slide
    {
        $slide = $this->createPartialMock(Slide::class, []);
        $slide->setData($data);

        return $slide;
    }
}
