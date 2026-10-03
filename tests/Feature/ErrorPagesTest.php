<?php

namespace Tests\Feature;

use Illuminate\Foundation\Exceptions\RegisterErrorViewPaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every error status gets the site's own error page with that status, never a 500 or the bare
 * "An Error Occurred" page (resources/views/errors).
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/_test-errors/{status}', fn (string $status) => abort((int) $status))
            ->whereNumber('status');
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function statuses(): array
    {
        return [
            '401' => [401, 'Connexion requise', 'Erreur 401'],
            '402' => [402, 'Accès indisponible', 'Erreur 402'],
            '403' => [403, 'Accès refusé', 'Erreur 403'],
            '404' => [404, 'Œuvre introuvable', 'Erreur 404'],
            '410 (generic)' => [410, 'Demande impossible', 'Erreur 410'],
            '502 (generic)' => [502, 'L’atelier est en désordre', 'Erreur 500'],
        ];
    }

    #[DataProvider('statuses')]
    public function test_each_status_gets_the_sites_error_page(int $status, string $title, string $code): void
    {
        $this->get('/_test-errors/'.$status)
            ->assertStatus($status)
            ->assertSee($title)
            ->assertSee($code)
            ->assertSee('error-page', false);
    }

    public function test_a_wrong_method_gets_the_sites_error_page(): void
    {
        $this->post('/services')
            ->assertStatus(405)
            ->assertSee('Demande impossible')
            ->assertSee('Erreur 405');
    }

    public function test_the_minimal_page_accepts_a_code_section_like_laravels_own_error_views(): void
    {
        (new RegisterErrorViewPaths)();   // what the exception handler does before rendering
        $html = Blade::render("@extends('errors::minimal')\n@section('code', '418')");

        $this->assertStringContainsString('Demande impossible', $html);
        $this->assertStringContainsString('Erreur 418', $html);
    }
}
