<?php

namespace Tests\Unit;

use App\Support\Phone\SomaliPhone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SomaliPhoneTest extends TestCase
{
    public static function goodNumbers(): array
    {
        return [
            ['615123456', '+252615123456'],
            ['0615123456', '+252615123456'],
            ['61 512 3456', '+252615123456'],
            ['+252 61 5123456', '+252615123456'],
            ['252615123456', '+252615123456'],
            ['00252615123456', '+252615123456'],
            ['+252-77-123-4567', '+252771234567'],
            ['907123456', '+252907123456'],
        ];
    }

    public static function badNumbers(): array
    {
        return [[''], ['12345'], ['815123456'], ['+254712345678'], ['61512345'], ['6151234567'], ['phone']];
    }

    #[DataProvider('goodNumbers')]
    public function test_normalizes_what_people_type(string $input, string $expected): void
    {
        $this->assertSame($expected, SomaliPhone::normalize($input));
    }

    #[DataProvider('badNumbers')]
    public function test_refuses_bad_numbers(string $input): void
    {
        $this->assertNull(SomaliPhone::normalize($input));
    }

    public function test_display_and_whatsapp_formats(): void
    {
        $this->assertSame('+252 61 512 3456', SomaliPhone::format('+252615123456'));
        $this->assertSame('252615123456', SomaliPhone::whatsappNumber('+252615123456'));
    }
}
