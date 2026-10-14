<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Test\Unit\Model;

use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\UrlInterface;
use Magento\MediaStorage\Model\File\Uploader;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Magento\Framework\Exception\LocalizedException;
use Zero\Banner\Model\ImageResizer;
use Zero\Banner\Model\ImageUploader;

#[AllowMockObjectsWithoutExpectations]
class ImageUploaderTest extends TestCase
{
    private WriteInterface&MockObject $media;
    private UploaderFactory $uploaderFactory;
    private ?Uploader $uploader = null;
    private ImageResizer&MockObject $resizer;

    protected function setUp(): void
    {
        $this->media = $this->createMock(WriteInterface::class);
        $this->media->method('getAbsolutePath')->willReturnCallback(static fn ($p) => '/var/www/pub/media/' . $p);
        $this->uploaderFactory = $this->createStub(UploaderFactory::class);
        $this->uploaderFactory->method('create')->willReturnCallback(fn () => $this->uploader);
        $this->resizer = $this->createMock(ImageResizer::class);
    }

    public function testUploadAllowsRasterImagesOnlyAndReturnsMediaUrl(): void
    {
        $this->uploader = $this->createMock(Uploader::class);
        $this->uploader->expects($this->once())->method('setAllowedExtensions')
            ->with($this->callback(static fn (array $ext) => !in_array('svg', $ext, true) && in_array('jpg', $ext, true)));
        $this->uploader->method('save')->willReturn([
            'file' => 'hero.jpg',
            'name' => 'hero.jpg',
            'path' => '/var/www/pub/media/zero_banner/tmp',
            'tmp_name' => '/tmp/php123',
        ]);

        $result = $this->uploaderService()->saveFileToTmpDir('image');

        $this->assertSame('https://shop.test/media/zero_banner/tmp/hero.jpg', $result['url']);
        $this->assertArrayNotHasKey('path', $result, 'filesystem paths are not exposed');
        $this->assertArrayNotHasKey('tmp_name', $result);
    }

    public function testMoveStripsDirectoryPartsFromName(): void
    {
        $this->media->method('isFile')->willReturnMap([['zero_banner/tmp/env.php', true]]);
        $this->media->method('isExist')->willReturn(false);
        $this->media->expects($this->once())->method('renameFile')
            ->with('zero_banner/tmp/env.php', 'zero_banner/env.php');

        $path = $this->uploaderService()->moveFileFromTmp('../../app/etc/env.php');

        $this->assertSame('/zero_banner/env.php', $path);
    }

    public function testMoveDoesNotOverwriteAnotherBannersImage(): void
    {
        $this->media->method('isFile')->willReturn(true);
        $this->media->method('isExist')->willReturnCallback(
            static fn (string $p) => in_array($p, ['zero_banner/hero.jpg', 'zero_banner/hero_1.jpg'], true)
        );
        $this->media->expects($this->once())->method('renameFile')
            ->with('zero_banner/tmp/hero.jpg', 'zero_banner/hero_2.jpg');

        $this->assertSame('/zero_banner/hero_2.jpg', $this->uploaderService()->moveFileFromTmp('hero.jpg'));
    }

    public function testMoveOfMissingUploadFailsInsteadOfSavingABrokenPath(): void
    {
        $this->media->method('isFile')->willReturn(false);
        $this->media->expects($this->never())->method('renameFile');

        $this->expectException(LocalizedException::class);
        $this->uploaderService()->moveFileFromTmp('hero.jpg');
    }

    public function testMoveFailureIsReported(): void
    {
        $this->media->method('isFile')->willReturn(true);
        $this->media->method('isExist')->willReturn(false);
        $this->media->method('renameFile')->willThrowException(new \RuntimeException('disk full'));

        $this->expectException(LocalizedException::class);
        $this->uploaderService()->moveFileFromTmp('hero.jpg');
    }

    public function testFreshUploadIsRecognisedByItsTmpUrl(): void
    {
        $uploader = $this->uploaderService();

        $this->assertTrue($uploader->isTmpUpload(['name' => 'a.jpg', 'url' => 'https://shop.test/media/zero_banner/tmp/a.jpg']));
        $this->assertFalse($uploader->isTmpUpload(['name' => 'a.jpg', 'url' => 'https://shop.test/media/zero_banner/a.jpg']));
        $this->assertFalse($uploader->isTmpUpload(['name' => 'x.jpg', 'url' => 'https://shop.test/media/catalog/product/w/j/x.jpg']));
        $this->assertFalse($uploader->isTmpUpload(['name' => 'a.jpg']));
    }

    public function testDeleteRemovesUploadAndItsResizedCopies(): void
    {
        $this->media->method('isFile')->willReturn(true);
        $this->media->expects($this->once())->method('delete')->with('zero_banner/hero.jpg');
        $this->resizer->expects($this->once())->method('clean')->with('zero_banner/hero.jpg');

        $this->uploaderService()->deleteImage('/zero_banner/hero.jpg');
    }

    public function testDeleteNeverTouchesFilesOutsideTheUploadDir(): void
    {
        $this->media->method('isFile')->willReturn(true);
        $this->media->expects($this->never())->method('delete');

        $uploader = $this->uploaderService();
        $uploader->deleteImage('/catalog/product/w/j/wj06-purple_main_1.jpg');
        $uploader->deleteImage('/zero_banner/../catalog/product/x.jpg');
        $uploader->deleteImage('/zero_banner/cache/1200x1500/zero_banner/a.jpg');
        $uploader->deleteImage(null);
    }

    public function testGalleryPickResolvesToAMediaPath(): void
    {
        $read = $this->createStub(\Magento\Framework\Filesystem\Directory\ReadInterface::class);
        $read->method('isFile')->willReturnCallback(static fn ($p) => $p === 'wysiwyg/summer sale.jpg');
        $uploader = $this->uploaderService($read);

        $this->assertSame('/wysiwyg/summer sale.jpg', $uploader->mediaPathFromUrl('https://shop.test/media/wysiwyg/summer%20sale.jpg'));
        $this->assertNull($uploader->mediaPathFromUrl('https://shop.test/media/wysiwyg/missing.jpg'), 'must exist');
        $this->assertNull($uploader->mediaPathFromUrl('https://shop.test/media/zero_banner/tmp/a.jpg'), 'tmp uploads are moved instead');
        $this->assertNull($uploader->mediaPathFromUrl('https://shop.test/media/zero_banner/cache/5x2/480q80/a.jpg'));
        $this->assertNull($uploader->mediaPathFromUrl('https://shop.test/media/../app/etc/env.php'));
        $this->assertNull($uploader->mediaPathFromUrl('https://shop.test/static/a.jpg'), 'outside pub/media');
    }

    private function uploaderService(?\Magento\Framework\Filesystem\Directory\ReadInterface $read = null): ImageUploader
    {
        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($this->media);
        $filesystem->method('getDirectoryRead')->willReturn($read ?? $this->media);
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturnMap([[UrlInterface::URL_TYPE_MEDIA, null, 'https://shop.test/media/']]);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new ImageUploader(
            $this->uploaderFactory,
            $filesystem,
            $this->createStub(LoggerInterface::class),
            $storeManager,
            $this->resizer
        );
    }
}
