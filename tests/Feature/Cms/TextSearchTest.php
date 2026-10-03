<?php

namespace Tests\Feature\Cms;

use App\Cms\Text;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Text searches with Text::like() on the test database (SQLite: LIKE has no escape character). */
class TextSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_with_wildcard_characters_find_their_rows(): void
    {
        DB::table('settings')->insert([['key' => 'IMG_2041.jpg', 'value' => 'a'], ['key' => '50% off', 'value' => 'b'], ['key' => 'autre', 'value' => 'c']]);

        $search = fn (string $term): array => DB::table('settings')->whereLike('key', Text::like($term), caseSensitive: false)->pluck('key')->all();

        $this->assertSame(['IMG_2041.jpg'], $search('img_2041'));
        $this->assertSame(['50% off'], $search('50%'));
        $this->assertSame([], $search('introuvable'));
    }
}
