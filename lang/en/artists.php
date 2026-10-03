<?php

/*
|--------------------------------------------------------------------------
| Ateliers Pehouet — artist pages (public), docs/ARTISTS.md §5 and §7
|--------------------------------------------------------------------------
|
| English mirror of lang/fr/artists.php (identical keys). French is the main language.
|
*/

return [

    // Header & footer link (public-site session) and the admin preview banner.
    'nav' => 'Artists',
    'preview' => 'Preview: this artist page is not published. Only signed-in administrators can see it.',

    // Availability of an artwork (App\Models\Artwork::AVAILABILITIES; "none" shows nothing).
    'availability' => [
        'available' => 'Available',
        'reserved' => 'Reserved',
        'sold' => 'Sold',
        'collection' => 'In a collection',
        'commission' => 'By commission',
    ],

    // Kind of exhibition (App\Models\Exhibition::KINDS).
    'kinds' => [
        'solo' => 'Solo exhibition',
        'group' => 'Group exhibition',
        'residency' => 'Residency',
        'fair' => 'Art fair',
        'other' => 'Event',
    ],

    // Status of an exhibition relative to today (App\Artists\Dates::status()).
    'status' => [
        'upcoming' => 'Upcoming',
        'current' => 'On view',
        'past' => 'Past',
    ],

    'a11y' => [
        'location' => 'Based in',
        'new_tab' => 'opens in a new tab',
    ],

    'counts' => [
        'artworks' => '{0} No works|{1} :count work|[2,*] :count works',
        'exhibitions' => '{0} No exhibitions|{1} :count exhibition|[2,*] :count exhibitions',
    ],

    // --- /artistes ---------------------------------------------------------------
    'index' => [
        'title' => 'Artists',
        'admin_edit' => 'Manage artists',
        'meta' => 'Meet the artists presented by Ateliers Pehouet: their path, a selection of works and their exhibitions, past and upcoming.',
        'eyebrow' => 'The atelier’s artists',
        'hero_title' => 'Artists',
        'lead' => 'A path, a body of work, exhibitions. Every artist presented by the atelier has a page of their own, designed like a hang.',
        'cta_discover' => 'Meet the artists',
        'cta_exhibit' => 'Exhibit with us',
        'list_title' => 'Featured artists',
        'count' => '{0} No artists yet|{1} :count artist|[2,*] :count artists',
        'empty' => [
            'eyebrow' => 'Coming soon',
            'title' => 'The first pages are on their way',
            'text' => 'The atelier is preparing its artists’ pages: biographies, selected works and exhibitions. Come back soon, or write to us to learn more.',
            'button' => 'Write to the atelier',
        ],
        'unavailable' => [
            'eyebrow' => 'Temporarily unavailable',
            'title' => 'The artists will be back shortly',
            'text' => 'The artist pages are temporarily unavailable. The rest of the site works as usual; please come back in a few minutes.',
            'button' => 'Write to the atelier',
        ],
        'cta' => [
            'title' => 'Are you an artist?',
            'text' => 'Showing your work, exhibiting, imagining a project together: let’s talk.',
            'button' => 'Write to the atelier',
            'secondary' => 'About the atelier',
        ],
    ],

    // Artist card (index grid and "Other artists").
    'card' => [
        'more' => 'View the page',
    ],

    // --- /artistes/{slug} ------------------------------------------------------------
    'show' => [
        'eyebrow' => 'Artist',
        'admin_edit' => 'Edit this artist',
        'portrait_alt' => 'Portrait of :name',
        'key_work' => 'Key work',
        'example' => 'Example page: fictional artist and works.',

        'actions' => [
            'works' => 'View the works',
            'exhibitions' => 'Exhibitions',
            'contact' => 'Contact the atelier',
        ],

        'facts' => [
            'label' => 'The artist at a glance',
            'artworks' => 'Works',
            'exhibitions' => 'Exhibitions',
            'discipline' => 'Discipline',
            'location' => 'Based in',
            'links' => 'Links',
        ],

        'bio' => [
            'eyebrow' => 'The artist',
            'title' => 'Biography',
            'sheet' => 'Fact sheet',
            'inquire' => 'Ask for information',
        ],

        'works' => [
            'eyebrow' => 'Selection',
            'title' => 'Selected works',
            'filter_label' => 'Filter the works by availability',
            'all' => 'All',
            'announce' => '{count} work(s) shown',
            'zoom' => 'Enlarge the image',
            'details' => 'About this work',
            'no_image' => 'Image coming soon',
        ],

        'exhibitions' => [
            'eyebrow' => 'Current & past',
            'title' => 'Exhibitions',
            'now' => 'On view & upcoming',
            'cv' => 'Exhibition history',
            'more' => 'Learn more',
        ],

        'more' => [
            'eyebrow' => 'Discover',
            'title' => 'Other artists',
            'nav_label' => 'Browse the artists',
            'prev' => 'Previous artist',
            'next' => 'Next artist',
            'all' => 'All artists',
        ],

        'cta' => [
            'title' => 'Interested in a work?',
            'text' => 'Availability, price or a commission: write to the atelier and we will get back to you.',
            'button' => 'Write to the atelier',
            'secondary' => 'All artists',
        ],
    ],

];
