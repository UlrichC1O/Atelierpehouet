<?php

namespace Tests\Feature\Cms;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/** Contact details and social links edited in the CMS win over the .env values (docs/CMS.md §4.1, §7.2). */
class SettingsTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    private function set(string $key, ?string $value): Setting
    {
        return Setting::query()->create(['key' => $key, 'value' => $value]);
    }

    public function test_settings_are_applied_to_the_config_and_shown_in_the_footer(): void
    {
        config(['atelier.contact.email' => '', 'atelier.contact.notify' => '', 'atelier.socials.instagram' => '']);
        $this->set('contact.email', 'bonjour@pehouet.test');
        $this->set('contact.phone', '+33 6 11 22 33 44');
        $this->set('socials.instagram', 'https://www.instagram.com/ateliers.pehouet');

        $this->get('/?lang=fr')
            ->assertOk()
            ->assertSee('mailto:bonjour@pehouet.test', false)
            ->assertSee('+33 6 11 22 33 44')
            ->assertSee('https://www.instagram.com/ateliers.pehouet', false);

        $this->assertSame('bonjour@pehouet.test', config('atelier.contact.email'));
        $this->assertSame('bonjour@pehouet.test', config('atelier.contact.notify'), 'an empty notify address falls back to the contact e-mail');
        $this->assertSame('https://www.instagram.com/ateliers.pehouet', config('atelier.socials.instagram'));
    }

    public function test_an_empty_row_hides_the_env_value_and_removing_it_restores_it(): void
    {
        config(['atelier.contact.email' => 'env@pehouet.test', 'atelier.contact.notify' => 'notify@pehouet.test']);
        $row = $this->set('contact.email', '');

        $this->get('/contact?lang=fr')->assertOk()->assertDontSee('env@pehouet.test');
        $this->assertSame('', config('atelier.contact.email'));
        $this->assertSame('notify@pehouet.test', config('atelier.contact.notify'));

        $row->delete();

        $this->get('/contact?lang=fr')->assertOk()->assertSee('env@pehouet.test');
        $this->assertSame('env@pehouet.test', config('atelier.contact.email'));
    }

    public function test_only_known_contact_and_social_keys_reach_the_config(): void
    {
        $this->set('contact.fax', '01 00 00 00 00');
        $this->set('socials.myspace', 'https://myspace.com/pehouet');
        $this->set('announcement.enabled', '1');
        $this->set('name', 'Autre nom');

        $this->get('/?lang=fr')->assertOk();

        $this->assertNull(config('atelier.contact.fax'));
        $this->assertNull(config('atelier.socials.myspace'));
        $this->assertSame('Ateliers Pehouet', config('atelier.name'));
        $this->assertSame('1', cms()->setting('announcement.enabled'));
    }

    public function test_quote_requests_are_e_mailed_to_the_address_set_in_the_cms(): void
    {
        config(['atelier.contact.notify' => 'env@pehouet.test']);
        $this->set('contact.notify', 'devis@pehouet.test');

        $this->get('/contact?lang=fr')->assertOk();

        $this->assertSame('devis@pehouet.test', config('atelier.contact.notify'));
    }
}
