<?php

/*
|--------------------------------------------------------------------------
| Ateliers Pehouet — brand configuration
|--------------------------------------------------------------------------
|
| Single source of truth for the brand: name, palette (sampled from the
| official logo), service categories, generative art styles and the
| Python art engine bridge. Contact details are read from the .env file
| so they can be changed without touching the code.
|
*/

return [

    'name' => 'Ateliers Pehouet',

    'short_name' => 'Pehouet',

    // Supported interface languages (first = default).
    'locales' => [
        'fr' => 'Français',
        'en' => 'English',
    ],

    // Empty values are hidden on the site, so no placeholder address is ever published.
    'contact' => [
        'email' => env('ATELIER_EMAIL', ''),
        'phone' => env('ATELIER_PHONE', ''),
        'whatsapp' => env('ATELIER_WHATSAPP', ''),
        'address' => env('ATELIER_ADDRESS', ''),
        // Where new quote requests are e-mailed (defaults to the public e-mail).
        'notify' => env('ATELIER_NOTIFY_EMAIL') ?: env('ATELIER_EMAIL', ''),
    ],

    // Social profiles — leave empty in .env to hide an icon.
    'socials' => [
        'instagram' => env('ATELIER_INSTAGRAM', ''),
        'facebook' => env('ATELIER_FACEBOOK', ''),
        'tiktok' => env('ATELIER_TIKTOK', ''),
        'youtube' => env('ATELIER_YOUTUBE', ''),
    ],

    /*
    | Logo palette. Core colours were sampled from the logo artwork; the
    | "bright"/"deep" tints exist only to keep text contrast accessible on
    | the black background. Mirrors public/css/01-tokens.css and
    | python/art_engine/palette.py — keep the three in sync.
    */
    'palette' => [
        'black' => '#000000',
        'ink' => '#08080b',
        'coal' => '#121216',
        'graphite' => '#1d1d23',
        'steel' => '#2c2c34',
        'smoke' => '#898789',
        'mist' => '#c4c2c5',
        'white' => '#fafcfd',
        'blue' => '#265fa5',
        'blue_bright' => '#4f8edc',
        'blue_deep' => '#173d6b',
        'yellow' => '#f8d449',
        'amber' => '#e3a94e',
        'orange' => '#c96338',
        'red' => '#b32c2b',
        'red_bright' => '#e8433b',
        'red_deep' => '#6e1a1a',
    ],

    // Accent keys a service/category may use (each has a .accent-{key} class).
    'accents' => ['blue', 'yellow', 'red', 'orange', 'amber', 'white'],

    // Service families, in display order. Labels live in lang/*/ui.php (ui.categories.*).
    'categories' => [
        'peinture' => ['accent' => 'red'],
        'design' => ['accent' => 'yellow'],
        'matiere' => ['accent' => 'blue'],
        'espaces' => ['accent' => 'orange'],
        'image' => ['accent' => 'white'],
        'communaute' => ['accent' => 'amber'],
    ],

    // Generative art styles provided by the Python engine (python/art_engine).
    // Labels/descriptions live in lang/*/generator.php (generator.styles.*).
    'art_styles' => ['pehouet', 'mondrian', 'prisme', 'mosaique', 'vitrail', 'eclats', 'soleil', 'tissage'],

    'art_engine' => [
        // HTTP micro-service started with `python3 -m art_engine serve` (from ./python).
        'url' => env('ART_ENGINE_URL', 'http://127.0.0.1:8765'),
        'timeout' => (float) env('ART_ENGINE_TIMEOUT', 2.5),
        // Fallback: run the engine's CLI directly when the service is not reachable.
        'cli' => (bool) env('ART_ENGINE_CLI', true),
        'python' => env('ART_ENGINE_PYTHON', 'python3'),
        'path' => base_path('python'),
        'sizes' => [400, 800, 1200],
    ],

    // Budget ranges offered on the quote form. Labels in lang/*/contact.php.
    'budgets' => ['small', 'medium', 'large', 'unsure'],

];
