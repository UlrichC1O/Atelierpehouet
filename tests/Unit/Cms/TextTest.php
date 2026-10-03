<?php

namespace Tests\Unit\Cms;

use App\Cms\Text;
use PHPUnit\Framework\TestCase;

/** Strings on their way into Postgres (docs/CMS.md §13 B7). */
class TextTest extends TestCase
{
    public function test_column_scrubs_strips_nul_bytes_and_cuts_to_the_column_size(): void
    {
        $this->assertNull(Text::column(null, 10));
        $this->assertSame('Fresque', Text::column("Fres\0que", 120));
        $this->assertSame('Été à l’atelier', Text::column('Été à l’atelier', 255));
        $this->assertSame('ééé', Text::column('éééé', 3), 'characters, not bytes');
        $this->assertSame('Caf?', Text::column("Caf\xE9", 10), 'invalid UTF-8 is replaced');
        $this->assertSame('', Text::column('abc', 0));
    }

    public function test_clean_walks_arrays_for_json_columns(): void
    {
        $this->assertSame(
            ['fr' => ['hero.title' => 'Avant', 'list' => ['un', 'deux']], 'n' => 3, 'b' => true, 'x' => null],
            Text::clean(["f\0r" => ['hero.title' => "Av\0ant", 'list' => ['un', 'deux']], 'n' => 3, 'b' => true, 'x' => null]),
        );
        $this->assertNull(Text::clean(new \stdClass));
    }

    public function test_like_escapes_the_wildcards(): void
    {
        $this->assertSame('%50\\%\\_off\\\\%', Text::like('50%_off\\', 'pgsql'));
        $this->assertSame('%fresque%', Text::like('fresque', 'pgsql'));
        // SQLite's LIKE has no escape character: the wildcards stay (they also match themselves).
        $this->assertSame('%50%_off%', Text::like("50%_o\0ff", 'sqlite'));
    }
}
