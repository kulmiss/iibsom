<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\City;
use App\Models\User;
use App\Support\Phone\SomaliPhone;
use App\Support\Safety\SpamGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Broken text (invalid UTF-8 bytes) must never crash a page: answer normally or 422. */
class BadInputTest extends TestCase
{
    use RefreshDatabase;

    private const BROKEN = "Toyota \xC3\x28 \xFF clean";

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_search_with_broken_text(): void
    {
        $this->get('/search?q='.rawurlencode(self::BROKEN))->assertStatus(400)->assertSee('could not be read');
        $this->getJson('/api/v1/ads?q='.rawurlencode(self::BROKEN))->assertStatus(400);
        $this->get('/search?q='.rawurlencode('Toyota Hilux, Muqdisho ✓ – €'))->assertOk(); // real Unicode is fine
    }

    public function test_spam_check_with_broken_text(): void
    {
        $this->assertNull(SpamGuard::badWord(self::BROKEN));
        $this->assertFalse(SpamGuard::hasLink(self::BROKEN));
    }

    public function test_posting_broken_text_is_refused_not_crashed(): void
    {
        $seller = User::factory()->completed()->create(['city_id' => City::where('slug', 'mogadishu')->value('id')]);

        $status = $this->actingAs($seller)->post('/post/cars', [
            'title' => self::BROKEN, 'description' => self::BROKEN.' more words here to pass the length',
            'price' => 100, 'condition' => 'used', 'city_id' => City::where('slug', 'mogadishu')->value('id'),
            'fields' => ['brand' => 'toyota', 'model' => 'Vitz', 'year' => 2012, 'fuel' => 'petrol', 'transmission' => 'automatic'],
        ])->getStatusCode();

        $this->assertSame(400, $status);
        $this->assertSame(0, Ad::count());
    }

    public function test_phone_with_broken_text(): void
    {
        $this->assertNull(SomaliPhone::normalize("\xFF\xFE"));
    }
}
