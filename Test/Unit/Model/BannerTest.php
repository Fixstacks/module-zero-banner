<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Test\Unit\Model;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Zero\Banner\Model\Banner;

#[AllowMockObjectsWithoutExpectations]
class BannerTest extends TestCase
{
    public function testIdentitiesAlwaysIncludeTheListTag(): void
    {
        // A new or re-enabled banner isn't on the cached homepage yet, so only the
        // list tag (which Block\Carousel always sets) can purge that page.
        $banner = $this->createPartialMock(Banner::class, ['getId']);
        $banner->method('getId')->willReturn(null);
        $this->assertSame(['zero_banner'], $banner->getIdentities());
    }

    public function testIdentitiesOfASavedBanner(): void
    {
        $banner = $this->createPartialMock(Banner::class, ['getId']);
        $banner->method('getId')->willReturn(7);
        $this->assertSame(['zero_banner', 'zero_banner_7'], $banner->getIdentities());
    }
}
