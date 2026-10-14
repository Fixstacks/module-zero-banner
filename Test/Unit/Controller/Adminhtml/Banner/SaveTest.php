<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Test\Unit\Controller\Adminhtml\Banner;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Zero\Banner\Controller\Adminhtml\Banner\Save;
use Zero\Banner\Model\Banner;
use Zero\Banner\Model\BannerFactory;
use Zero\Banner\Model\ImageUploader;
use Zero\Banner\Model\ResourceModel\Banner as BannerResource;
use Zero\Banner\Model\ResourceModel\Slide\Collection as SlideCollection;
use Zero\Banner\Model\ResourceModel\Slide\CollectionFactory as SlideCollectionFactory;
use Zero\Banner\Model\Slide;
use Zero\Banner\Model\SlideFactory;
use Zero\Banner\Model\SlidePicture;

#[AllowMockObjectsWithoutExpectations]
class SaveTest extends TestCase
{
    private Banner&MockObject $banner;
    private BannerResource&MockObject $resource;
    private ImageUploader&MockObject $uploader;
    private MessageManager&MockObject $messages;
    private DataPersistorInterface&MockObject $persistor;
    private EventManager&MockObject $events;

    /**
     * @var Slide[] The banner's stored slides, by id.
     */
    private array $existing = [];

    /**
     * @var Slide[] Slides the controller created.
     */
    private array $created = [];
    private ?string $redirectPath = null;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(BannerResource::class);
        $this->banner = $this->createPartialMock(Banner::class, ['load', 'save', 'getResource', 'cleanModelCache']);
        $this->banner->method('getResource')->willReturn($this->resource);
        $this->banner->method('load')->willReturnCallback(function ($id) {
            $this->banner->setId($id === 99 ? null : $id);
            return $this->banner;
        });
        $this->banner->method('save')->willReturnCallback(function () {
            $this->banner->getId() ?: $this->banner->setId(7);
            return $this->banner;
        });

        $this->uploader = $this->createMock(ImageUploader::class);
        $this->uploader->method('isTmpUpload')->willReturnCallback(
            static fn (array $file) => str_contains((string) ($file['url'] ?? ''), '/zero_banner/tmp/')
        );
        $this->messages = $this->createMock(MessageManager::class);
        $this->persistor = $this->createMock(DataPersistorInterface::class);
        $this->events = $this->createMock(EventManager::class);
    }

    public function testNewBannerIsCreatedWithItsSlidesInPostedOrder(): void
    {
        $this->uploader->method('moveFileFromTmp')->willReturnCallback(static fn ($name) => '/zero_banner/' . $name);
        $this->resource->expects($this->once())->method('commit');

        $this->post([
            'banner_id' => '',
            'name' => 'Sale',
            'identifier' => 'sale',
            'aspect_ratio_mobile' => '',
            'slides' => [
                ['position' => '2', 'heading' => 'Second', 'image' => [self::upload('b.jpg')]],
                ['position' => '1', 'heading' => 'First', 'image' => [self::upload('a.jpg')], 'date_from' => ''],
            ],
        ]);

        $this->assertSame('Sale', $this->banner->getData('name'));
        $this->assertNull($this->banner->getData('aspect_ratio_mobile'), 'empty = same as desktop');
        $this->assertSame(['First', 'Second'], array_map(static fn ($s) => $s->getData('heading'), $this->created));
        $this->assertSame([0, 1], array_map(static fn ($s) => $s->getData('position'), $this->created));
        $this->assertSame([7, 7], array_map(static fn ($s) => $s->getData('banner_id'), $this->created));
        $this->assertSame('/zero_banner/a.jpg', $this->created[0]->getData('image'));
        $this->assertNull($this->created[0]->getData('date_from'));
        $this->assertSame('zero_banner/banner/index', $this->redirectPath);
    }

    public function testStoredSlidesAreUpdatedAndMissingOnesDeleted(): void
    {
        $kept = $this->storedSlide(1, ['heading' => 'Old', 'image' => '/zero_banner/kept.jpg']);
        $removed = $this->storedSlide(2, ['heading' => 'Gone']);
        $kept->expects($this->once())->method('save');
        $removed->expects($this->once())->method('delete');
        $this->events->expects($this->once())->method('dispatch')->with('clean_cache_by_tags');

        $this->post(['banner_id' => '4', 'slides' => [
            ['slide_id' => '1', 'heading' => 'New', 'image' => [['name' => 'kept.jpg', 'url' => 'https://shop.test/media/zero_banner/kept.jpg']]],
        ]]);

        $this->assertSame('New', $kept->getData('heading'));
        $this->assertSame('/zero_banner/kept.jpg', $kept->getData('image'));
        $this->assertSame([], $this->created);
    }

    public function testUnchangedCatalogImageSurvivesResave(): void
    {
        // Migrated slides point at catalog photos: posting one back must not try
        // a tmp move (which used to store a nonexistent /zero_banner/<name>).
        $slide = $this->storedSlide(1, ['image' => '/catalog/product/w/j/wj06.jpg']);
        $this->uploader->expects($this->never())->method('moveFileFromTmp');

        $this->post(['banner_id' => '4', 'slides' => [
            ['slide_id' => '1', 'image' => [['name' => 'wj06.jpg', 'url' => 'https://shop.test/media/catalog/product/w/j/wj06.jpg']]],
        ]]);

        $this->assertSame('/catalog/product/w/j/wj06.jpg', $slide->getData('image'));
    }

    public function testImagePickedFromTheGalleryIsUsed(): void
    {
        $slide = $this->storedSlide(1, ['image' => '/zero_banner/old.jpg']);
        $this->uploader->method('mediaPathFromUrl')->willReturn('/wysiwyg/summer.jpg');

        $this->post(['banner_id' => '4', 'slides' => [
            ['slide_id' => '1', 'image' => [['name' => 'summer.jpg', 'url' => 'https://shop.test/media/wysiwyg/summer.jpg']]],
        ]]);

        $this->assertSame('/wysiwyg/summer.jpg', $slide->getData('image'));
    }

    public function testRemovedImageIsCleared(): void
    {
        $slide = $this->storedSlide(1, ['image' => '/zero_banner/a.jpg', 'mobile_image' => '/zero_banner/m.jpg']);

        $this->post(['banner_id' => '4', 'slides' => [['slide_id' => '1', 'image' => [['name' => 'a.jpg', 'url' => 'x']]]]]);

        $this->assertSame('/zero_banner/a.jpg', $slide->getData('image'));
        $this->assertNull($slide->getData('mobile_image'));
    }

    public function testAnotherBannersSlideIdCreatesANewSlide(): void
    {
        $this->post(['banner_id' => '4', 'slides' => [['slide_id' => '555', 'heading' => 'X']]]);

        $this->assertCount(1, $this->created, 'slide 555 is not this banner\'s: never updated through it');
    }

    public function testFailureRollsBackAndKeepsTheForm(): void
    {
        $this->uploader->method('moveFileFromTmp')
            ->willThrowException(new LocalizedException(__('The uploaded image was not found. Please upload it again.')));
        $this->resource->expects($this->once())->method('rollBack');
        $this->resource->expects($this->never())->method('commit');
        $this->messages->expects($this->once())->method('addErrorMessage');
        $this->persistor->expects($this->once())->method('set')
            ->with('zero_banner', $this->callback(static fn (array $d) => $d['name'] === 'Sale'));
        $this->events->expects($this->never())->method('dispatch');

        $this->post(['banner_id' => '4', 'name' => 'Sale', 'slides' => [['image' => [self::upload('a.jpg')]]]]);

        $this->assertSame('zero_banner/banner/edit', $this->redirectPath);
    }

    public function testMissingBannerIsReported(): void
    {
        $this->banner->expects($this->never())->method('save');
        $this->messages->expects($this->once())->method('addErrorMessage');

        $this->post(['banner_id' => '99']);

        $this->assertSame('zero_banner/banner/index', $this->redirectPath);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function storedSlide(int $id, array $data): Slide&MockObject
    {
        $slide = $this->createPartialMock(Slide::class, ['save', 'delete']);
        $slide->setData($data + ['slide_id' => $id]);
        $slide->setId($id);
        $this->existing[$id] = $slide;

        return $slide;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function post(array $data): void
    {
        $request = $this->createStub(HttpRequest::class);
        $request->method('getPostValue')->willReturn($data);

        $redirect = $this->createStub(Redirect::class);
        $redirect->method('setPath')->willReturnCallback(function (string $path) use ($redirect) {
            $this->redirectPath = $path;
            return $redirect;
        });
        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);

        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getMessageManager')->willReturn($this->messages);
        $context->method('getEventManager')->willReturn($this->events);

        $bannerFactory = $this->createStub(BannerFactory::class);
        $bannerFactory->method('create')->willReturn($this->banner);

        $slideFactory = $this->createStub(SlideFactory::class);
        $slideFactory->method('create')->willReturnCallback(function () {
            $slide = $this->createPartialMock(Slide::class, ['save', 'delete']);
            $this->created[] = $slide;
            return $slide;
        });

        $collection = $this->createMock(SlideCollection::class);
        $collection->method('addBannerFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturnCallback(fn () => new \ArrayIterator($this->existing));
        $collectionFactory = $this->createStub(SlideCollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        (new Save(
            $context,
            $bannerFactory,
            $slideFactory,
            $collectionFactory,
            $this->uploader,
            $this->createStub(SlidePicture::class),
            $this->persistor,
            $this->createStub(LoggerInterface::class)
        ))->execute();
    }

    /**
     * @return array<string, string> The uploader's entry right after an upload.
     */
    private static function upload(string $name): array
    {
        return ['name' => $name, 'file' => $name, 'url' => 'https://shop.test/media/zero_banner/tmp/' . $name];
    }
}
