<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Setup\Patch\Data;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * The demo store's partner slide: Vilmate (vilmate.com, Zero's development
 * partner, with their consent) as the last slide of the homepage hero, in
 * English and in Arabic. Only where the demo's homepage banners exist; a
 * fresh store gets the slide from CreateHomepageHero instead. The text is
 * the slide's own and can be edited in Content > Banners.
 */
class AddVilmatePartnerSlide implements DataPatchInterface
{
    public const MEDIA_DIR = 'zero_banner/vilmate';
    public const IMAGES = ['vilmate-partner-desktop.jpg', 'vilmate-partner-mobile.jpg'];
    public const LINK = 'https://vilmate.com/';

    /** banner identifier => slide text */
    public const SLIDES = [
        'homepage-hero' => [
            'eyebrow' => 'Built with Vilmate',
            'heading' => 'Your e-commerce development partner.',
            'text' => 'Strategy, design and development for online stores, from the storefront to every integration.',
            'button_text' => 'Talk to Vilmate',
        ],
        'homepage-hero-ar' => [
            'eyebrow' => 'بالتعاون مع Vilmate',
            'heading' => 'شريكك في تطوير التجارة الإلكترونية.',
            'text' => 'الاستراتيجية والتصميم والتطوير للمتاجر الإلكترونية، من الواجهة إلى كل عمليات التكامل.',
            'button_text' => 'تواصل مع Vilmate',
        ],
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly Filesystem $filesystem
    ) {
    }

    public function apply(): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $banners = $connection->fetchPairs(
            $connection->select()
                ->from($this->moduleDataSetup->getTable('zero_banner_entity'), ['identifier', 'banner_id'])
                ->where('identifier IN (?)', array_keys(self::SLIDES))
        );
        if (!$banners) {
            return;
        }
        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $media->create(self::MEDIA_DIR);
        foreach (self::IMAGES as $image) {
            $media->writeFile(
                self::MEDIA_DIR . '/' . $image,
                (string) file_get_contents(dirname(__DIR__, 2) . '/fixtures/vilmate/' . $image)
            );
        }
        $slides = $this->moduleDataSetup->getTable('zero_banner_slide');
        foreach ($banners as $identifier => $bannerId) {
            $exists = $connection->fetchOne(
                $connection->select()->from($slides, ['slide_id'])
                    ->where('banner_id = ?', (int) $bannerId)
                    ->where('link_url = ?', self::LINK)
            );
            if ($exists) {
                continue;
            }
            $position = (int) $connection->fetchOne(
                $connection->select()->from($slides, ['MAX(position)'])->where('banner_id = ?', (int) $bannerId)
            ) + 1;
            $connection->insert($slides, self::SLIDES[$identifier] + [
                'banner_id' => (int) $bannerId,
                'is_active' => 1,
                'position' => $position,
                'image' => '/' . self::MEDIA_DIR . '/' . self::IMAGES[0],
                'mobile_image' => '/' . self::MEDIA_DIR . '/' . self::IMAGES[1],
                'image_fit' => 'cover',
                'image_position' => 'center',
                'content_position' => 'left',
                'alt' => 'Vilmate',
                'link_url' => self::LINK,
            ]);
        }
    }

    public static function getDependencies(): array
    {
        return [MigrateToBannersWithSlides::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
