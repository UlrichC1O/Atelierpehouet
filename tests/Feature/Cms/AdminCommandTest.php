<?php

namespace Tests\Feature\Cms;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** php artisan atelier:admin — create or update an administrator (docs/CMS.md §4.6). */
class AdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_administrator(): void
    {
        $this->artisan('atelier:admin', ['email' => ' Owner@Pehouet.TEST ', '--password' => 'un-mot-de-passe-solide'])
            ->expectsOutputToContain('Administrator created: owner@pehouet.test')
            ->assertSuccessful();

        $user = User::query()->where('email', 'owner@pehouet.test')->firstOrFail();
        $this->assertSame('owner', $user->name);
        $this->assertTrue(Hash::check('un-mot-de-passe-solide', $user->password));
    }

    public function test_it_updates_an_existing_administrator(): void
    {
        User::factory()->create(['email' => 'owner@pehouet.test', 'name' => 'Ancien nom']);

        $this->artisan('atelier:admin', ['email' => 'owner@pehouet.test', '--name' => 'Atelier Pehouet', '--password' => 'nouveau-mot-de-passe'])
            ->expectsOutputToContain('Administrator updated')
            ->assertSuccessful();

        $user = User::query()->sole();
        $this->assertSame('Atelier Pehouet', $user->name);
        $this->assertTrue(Hash::check('nouveau-mot-de-passe', $user->password));
    }

    public function test_it_asks_for_the_password_secretly(): void
    {
        $this->artisan('atelier:admin', ['email' => 'owner@pehouet.test'])
            ->expectsQuestion('Password (at least 10 characters)', 'secret-tres-long')
            ->expectsQuestion('Repeat the password', 'secret-tres-long')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('secret-tres-long', User::query()->sole()->password));

        $this->artisan('atelier:admin', ['email' => 'owner@pehouet.test'])
            ->expectsQuestion('Password (at least 10 characters)', 'secret-tres-long')
            ->expectsQuestion('Repeat the password', 'autre-chose-long')
            ->expectsOutputToContain('differ')
            ->assertFailed();
    }

    public function test_it_refuses_bad_input(): void
    {
        $this->artisan('atelier:admin', ['email' => 'pas-un-email', '--password' => 'un-mot-de-passe-solide'])->assertFailed();
        $this->artisan('atelier:admin', ['email' => 'owner@pehouet.test', '--password' => 'court'])
            ->expectsOutputToContain('at least 10 characters')
            ->assertFailed();
        $this->artisan('atelier:admin', ['email' => 'owner@pehouet.test', '--no-interaction' => true])->assertFailed();

        $this->assertSame(0, User::query()->count());
    }

    public function test_it_explains_an_unmigrated_database(): void
    {
        Schema::drop('users');

        $this->artisan('atelier:admin', ['email' => 'owner@pehouet.test', '--password' => 'un-mot-de-passe-solide'])
            ->expectsOutputToContain('php artisan migrate')
            ->assertFailed();
    }
}
