<?php

namespace Engine\Cloudinary\Tests;

use Engine\Cloudinary\Cloudinary;
use PHPUnit\Framework\TestCase;

/**
 * Only Cloudinary::url() is tested here — pure string building, no
 * network call. upload()/destroy() need a real Cloudinary account (none
 * available in this environment), same confidence tier documented on
 * the class itself.
 */
final class CloudinaryTest extends TestCase
{
    public function testUrlWithNoTransformations(): void
    {
        $this->assertSame(
            'https://res.cloudinary.com/demo/image/upload/avatar',
            Cloudinary::url('demo', 'avatar'),
        );
    }

    public function testUrlWithShorthandTransformations(): void
    {
        $this->assertSame(
            'https://res.cloudinary.com/demo/image/upload/w_400,h_400,c_fill,q_auto/avatar',
            Cloudinary::url('demo', 'avatar', [
                'width' => 400,
                'height' => 400,
                'crop' => 'fill',
                'quality' => 'auto',
            ]),
        );
    }

    public function testUrlWithUnknownParameterFallsBackToItsOwnName(): void
    {
        $this->assertSame(
            'https://res.cloudinary.com/demo/image/upload/r_20,dpr_2.0/avatar',
            Cloudinary::url('demo', 'avatar', ['radius' => 20, 'dpr' => '2.0']),
        );
    }
}
