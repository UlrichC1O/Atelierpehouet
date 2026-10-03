<?php

namespace App\Http\Controllers\Admin;

use App\Cms\DatabaseHealth;
use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * GET /admin (admin.dashboard) — the admin's home (docs/CMS.md §7.2, minimal version until
 * admin-shell adds the statistics and the activity in phase 2): a greeting, the pending database
 * updates with their button (§13 F30), the unread messages and a card for every section.
 *
 * It sits behind cms.ready, so the CMS migrations ran; the banner shows the other pending ones
 * (e.g. a later release's). A database hiccup never breaks it: counts fall back to nothing.
 */
final class DashboardController extends Controller
{
    /**
     * Sections of the admin, in the sidebar's order: key ⇒ [route, icon, accent]. Label
     * admin.nav.{key}, description admin.dashboard.sections.{key}.
     */
    private const SECTIONS = [
        'texts' => ['admin.texts.index', 'text', 'yellow'],
        'services' => ['admin.services.index', 'services', 'red'],
        'media' => ['admin.media.index', 'image', 'blue'],
        'gallery' => ['admin.gallery.index', 'gallery', 'amber'],
        'pages' => ['admin.pages.index', 'page', 'orange'],
        'artists' => ['admin.artists.index', 'palette', 'orange'],
        'messages' => ['admin.messages.index', 'inbox', 'red'],
        'settings' => ['admin.settings.edit', 'settings', 'white'],
        'account' => ['admin.account.edit', 'user', 'blue'],
        'maintenance' => ['admin.maintenance', 'database', 'yellow'],
    ];

    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $pending = MaintenanceController::migrationState()['pending'] ?? [];
        $unread = $this->unread();

        return view('admin.dashboard.index', [
            'greeting' => __('admin.dashboard.greeting.'.$this->period(), ['name' => trim((string) $user->name)]),
            'pendingCount' => count($pending),
            'unread' => $unread,
            'sections' => $this->sections($unread),
        ]);
    }

    /** Morning, afternoon or evening, in the application's timezone. */
    private function period(): string
    {
        $hour = (int) now()->format('G');

        return match (true) {
            $hour >= 5 && $hour < 12 => 'morning',
            $hour >= 12 && $hour < 18 => 'afternoon',
            default => 'evening',
        };
    }

    /** Unread contact requests; 0 when the database cannot tell (through the circuit breaker when it exists). */
    private function unread(): int
    {
        $count = fn (): int => ContactMessage::query()->unread()->count();

        return (int) rescue(
            fn () => class_exists(DatabaseHealth::class) ? app(DatabaseHealth::class)->attempt($count, 0) : $count(),
            0,
            false,
        );
    }

    /**
     * The section cards (only the sections whose routes exist: the artist pages are a separate module).
     *
     * @return list<array{key: string, url: string, label: string, text: string, icon: string, accent: string, badge: int}>
     */
    private function sections(int $unread): array
    {
        $sections = [];

        foreach (self::SECTIONS as $key => [$route, $icon, $accent]) {
            if (! Route::has($route)) {
                continue;
            }

            $label = $key === 'artists' && app('translator')->has('admin_artists.nav') ? __('admin_artists.nav') : __('admin.nav.'.$key);

            $sections[] = [
                'key' => $key,
                'url' => route($route),
                'label' => $label,
                'text' => __('admin.dashboard.sections.'.$key),
                'icon' => $icon,
                'accent' => $accent,
                'badge' => $key === 'messages' ? $unread : 0,
            ];
        }

        return $sections;
    }
}
