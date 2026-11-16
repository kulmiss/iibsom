<?php

namespace Tests\Unit;

use Tests\TestCase;

class HelpersTest extends TestCase
{
    public function test_translated_picks_language_then_english(): void
    {
        app()->setLocale('so');
        $this->assertSame('Baabuur', translated(['en' => 'Cars', 'so' => 'Baabuur']));
        $this->assertSame('Cars', translated(['en' => 'Cars']));
        $this->assertSame('plain', translated('plain'));
        $this->assertSame('', translated(null));
    }

    public function test_icon_keeps_inner_shape_sizes(): void
    {
        $svg = (string) icon('smartphone', 'size-6');

        $this->assertStringStartsWith('<svg class="size-6" aria-hidden="true"', $svg);
        $this->assertStringContainsString('<rect width="14" height="20"', $svg);
        $this->assertStringNotContainsString('@license', $svg);
    }

    public function test_unknown_icon_falls_back_to_tag(): void
    {
        $this->assertSame((string) icon('tag'), (string) icon('does-not-exist'));
    }

    public function test_icon_escapes_class(): void
    {
        $this->assertStringContainsString('class="a&quot;b"', (string) icon('tag', 'a"b'));
    }
}
