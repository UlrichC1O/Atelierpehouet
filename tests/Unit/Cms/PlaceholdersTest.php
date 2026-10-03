<?php

namespace Tests\Unit\Cms;

use App\Cms\Placeholders;
use PHPUnit\Framework\TestCase;

/** The texts editor keeps the placeholders of the original lines (docs/CMS.md §4.6). */
class PlaceholdersTest extends TestCase
{
    public function test_missing_colon_and_brace_placeholders_are_reported(): void
    {
        $this->assertSame([':count', '{count}'], Placeholders::missing(':count services ({count} affichés)', 'Des services'));
        $this->assertSame([':name'], Placeholders::missing('Bonjour :name, merci :count fois', 'Merci :count fois'));
        $this->assertSame([], Placeholders::missing(':count services', 'Nos :count services'));
    }

    public function test_case_variants_and_non_placeholders_are_tolerated(): void
    {
        $this->assertSame([], Placeholders::missing(':name arrive', ':Name arrive'));
        $this->assertSame([], Placeholders::missing('Ouvert de 9:30 à 18:00 — https://pehouet.fr', 'Ouvert le matin'));
        $this->assertSame([], Placeholders::missing('{0} Aucun|{1} Un|[2,*] Plusieurs', '{0} None|{1} One|[2,*] Many'));
    }

    public function test_pluralised_lines_keep_their_segments(): void
    {
        $this->assertSame(['|'], Placeholders::missing('{1} :count œuvre|[2,*] :count œuvres', ':count œuvres'));
        $this->assertSame(['|'], Placeholders::missing('Simple', 'Un|Deux'));
    }
}
