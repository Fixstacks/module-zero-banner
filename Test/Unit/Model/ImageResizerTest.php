<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Test\Unit\Model;

use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Image\Adapter\AbstractAdapter;
use Magento\Framework\Image\AdapterFactory;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Zero\Banner\Model\Config;
use Zero\Banner\Model\ImageResizer;

#[AllowMockObjectsWithoutExpectations]
class ImageResizerTest extends TestCase
{
    private WriteInterface&MockObject $media;
    private AbstractAdapter&MockObject $adapter;
    private string $dir;

    /**
     * @var array<string, int> media path => mtime
     */
    private array $files = [];

    protected function setUp(): void
    {
        // A real 2000×1000 source: getVariants() reads its size with getimagesize().
        $this->dir = sys_get_temp_dir() . '/zero-banner-test-' . uniqid();
        mkdir($this->dir . '/zero_banner', 0777, true);
        imagejpeg(imagecreatetruecolor(2000, 1000), $this->dir . '/zero_banner/hero.jpg');

        $this->media = $this->createMock(WriteInterface::class);
        $this->media->method('getAbsolutePath')->willReturnCallback(fn ($p) => $this->dir . '/' . $p);
        $this->media->method('isFile')->willReturnCallback(fn ($p) => isset($this->files[$p]));
        $this->media->method('stat')->willReturnCallback(fn ($p) => ['mtime' => $this->files[$p]]);
        $this->adapter = $this->createMock(AbstractAdapter::class);
        $this->files['zero_banner/hero.jpg'] = 100;
    }

    protected function tearDown(): void
    {
        unlink($this->dir . '/zero_banner/hero.jpg');
        rmdir($this->dir . '/zero_banner');
        rmdir($this->dir);
    }

    public function testCropBoxCentersTheTargetRatio(): void
    {
        $resizer = $this->resizer();

        // 2000×1000 (2:1) → 16:9: cut 222px off the sides, centered.
        $this->assertSame([[0, 111, 111, 0], 1778, 1000], $resizer->cropBox(2000, 1000, [16, 9]));
        // 1274×1580 portrait → 5:2: cut height, centered.
        $this->assertSame([[535, 0, 0, 535], 1274, 510], $resizer->cropBox(1274, 1580, [5, 2]));
    }

    public function testCropKeepsTheFocalPoint(): void
    {
        $resizer = $this->resizer();

        // Too tall for 5:2: top/bottom choose which band stays.
        $this->assertSame([[0, 0, 0, 1070], 1274, 510], $resizer->cropBox(1274, 1580, [5, 2], 'top'));
        $this->assertSame([[1070, 0, 0, 0], 1274, 510], $resizer->cropBox(1274, 1580, [5, 2], 'bottom'));
        // Too wide for 16:9: left/right choose; "top" doesn't apply to this axis.
        $this->assertSame([[0, 0, 222, 0], 1778, 1000], $resizer->cropBox(2000, 1000, [16, 9], 'left'));
        $this->assertSame([[0, 222, 0, 0], 1778, 1000], $resizer->cropBox(2000, 1000, [16, 9], 'right'));
        $this->assertSame([[0, 111, 111, 0], 1778, 1000], $resizer->cropBox(2000, 1000, [16, 9], 'top'));
        // A percentage, as CSS object-position: 1920×1080 → 3:5 keeps 648px, 61% of the 1272 cut on the left.
        $this->assertSame([[0, 776, 496, 0], 648, 1080], $resizer->cropBox(1920, 1080, [3, 5], '61'));
    }

    public function testFocalPointGetsItsOwnCacheDir(): void
    {
        $variants = $this->resizer()->getVariants('/zero_banner/hero.jpg', [16, 9], [1024], 'left');

        $this->assertStringContainsString('/cache/16x9-left/1024q80/', $variants[0]['url']);
    }

    public function testWholeImageIsResizedWithoutCropping(): void
    {
        $this->adapter->expects($this->never())->method('crop');
        $resized = [];
        $this->adapter->method('resize')->willReturnCallback(function ($w, $h) use (&$resized) {
            $resized[] = [$w, $h];
        });

        $variants = $this->resizer()->getVariants('/zero_banner/hero.jpg', null, [1024, 4096]);

        $this->assertSame([[1024, 512], [2000, 1000]], $resized);

        $this->assertSame([1024, 2000], array_column($variants, 'width'), 'up to the source width, no upscale');
        $this->assertSame([512, 1000], array_column($variants, 'height'), 'the image\'s own proportions');
        $this->assertStringContainsString('/cache/original/1024q80/', $variants[0]['url']);
    }

    public function testVariantsAreCroppedResizedAndNeverUpscaled(): void
    {
        $this->adapter->expects($this->exactly(2))->method('crop')->with(0, 111, 111, 0);
        $resized = [];
        $this->adapter->method('resize')->willReturnCallback(function ($w, $h) use (&$resized) {
            $resized[] = [$w, $h];
        });

        // Crop is 1778 wide: 2560 is dropped, 1778 (the crop's own width) is the largest.
        $variants = $this->resizer()->getVariants('/zero_banner/hero.jpg', [16, 9], [1024, 2560]);

        $this->assertSame([[1024, 576], [1778, 1000]], $resized);
        $this->assertSame([1024, 1778], array_column($variants, 'width'));
        $this->assertSame(
            'https://shop.test/media/zero_banner/cache/16x9/1024q80/zero_banner/hero.jpg',
            $variants[0]['url']
        );
        $this->assertNull($variants[0]['webp'], 'WebP disabled in this test');
    }

    public function testFreshCopyIsReused(): void
    {
        $this->files['zero_banner/cache/16x9/1024q80/zero_banner/hero.jpg'] = 200;
        $this->adapter->expects($this->never())->method('resize');

        $this->resizer()->getVariants('/zero_banner/hero.jpg', [16, 9], [1024]);
    }

    public function testCopyOlderThanTheSourceIsRebuilt(): void
    {
        $this->files['zero_banner/hero.jpg'] = 300;
        $this->files['zero_banner/cache/16x9/1024q80/zero_banner/hero.jpg'] = 200;
        $this->adapter->expects($this->once())->method('resize');

        $this->resizer()->getVariants('/zero_banner/hero.jpg', [16, 9], [1024]);
    }

    public function testMissingSourceGivesNoVariants(): void
    {
        $this->adapter->expects($this->never())->method('open');

        $this->assertNull($this->resizer()->getVariants('/zero_banner/gone.jpg', [16, 9], [1024]));
    }

    public function testResizeFailureFallsBackToTheOriginal(): void
    {
        $this->adapter->method('open')->willThrowException(new \RuntimeException('corrupt'));

        $variants = $this->resizer()->getVariants('/zero_banner/hero.jpg', [16, 9], [1024]);

        $this->assertSame('https://shop.test/media/zero_banner/hero.jpg', $variants[0]['url']);
    }

    private function resizer(): ImageResizer
    {
        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($this->media);
        $adapterFactory = $this->createStub(AdapterFactory::class);
        $adapterFactory->method('create')->willReturn($this->adapter);
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturnMap([[UrlInterface::URL_TYPE_MEDIA, null, 'https://shop.test/media/']]);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $config = $this->createStub(Config::class);
        $config->method('getQuality')->willReturn(80);
        $config->method('isWebpEnabled')->willReturn(false);

        return new ImageResizer(
            $filesystem,
            $adapterFactory,
            $storeManager,
            $config,
            $this->createStub(LoggerInterface::class)
        );
    }
}
