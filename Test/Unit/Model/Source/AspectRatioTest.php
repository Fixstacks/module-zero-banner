<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Test\Unit\Model\Source;

use PHPUnit\Framework\TestCase;
use Zero\Banner\Model\Source\AspectRatio;

class AspectRatioTest extends TestCase
{
    public function testParse(): void
    {
        $this->assertSame([16, 9], AspectRatio::parse('16:9'));
        $this->assertSame([4, 5], AspectRatio::parse('4:5'));
        $this->assertNull(AspectRatio::parse('7:3'), 'only the offered ratios');
        $this->assertNull(AspectRatio::parse(''));
        $this->assertNull(AspectRatio::parse(null));
    }
}
