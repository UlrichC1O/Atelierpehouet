<?php

/*
|--------------------------------------------------------------------------
| Ateliers Pehouet — artist pages (public), docs/ARTISTS.md §5 and §7
|--------------------------------------------------------------------------
|
| /artistes (index) and /artistes/{slug} (one artist: biography, works, exhibitions).
| Editable in the CMS "Textes des pages". lang/en/artists.php mirrors this structure key for key.
| Pluralised lines use "|" with trans_choice(); {count} is filled in by effects.js (filters).
|
*/

return [

    // Header & footer link (public-site session) and the admin preview banner.
    'nav' => 'Artistes',
    'preview' => "Aperçu\u{202F}: cette page d’artiste n’est pas publiée. Seuls les administrateurs connectés la voient.",

    // Availability of an artwork (App\Models\Artwork::AVAILABILITIES; "none" shows nothing).
    'availability' => [
        'available' => 'Disponible',
        'reserved' => 'Réservée',
        'sold' => 'Vendue',
        'collection' => 'En collection',
        'commission' => 'Sur commande',
    ],

    // Kind of exhibition (App\Models\Exhibition::KINDS).
    'kinds' => [
        'solo' => 'Exposition personnelle',
        'group' => 'Exposition collective',
        'residency' => 'Résidence',
        'fair' => 'Foire d’art',
        'other' => 'Événement',
    ],

    // Status of an exhibition relative to today (App\Artists\Dates::status()).
    'status' => [
        'upcoming' => 'À venir',
        'current' => 'En cours',
        'past' => 'Passée',
    ],

    'a11y' => [
        'location' => 'Basé·e à',
        'new_tab' => 's’ouvre dans un nouvel onglet',
    ],

    'counts' => [
        'artworks' => '{0} Aucune œuvre|{1} :count œuvre|[2,*] :count œuvres',
        'exhibitions' => '{0} Aucune exposition|{1} :count exposition|[2,*] :count expositions',
    ],

    // --- /artistes ---------------------------------------------------------------
    'index' => [
        'title' => 'Artistes',
        'admin_edit' => 'Gérer les artistes',
        'meta' => "Découvrez les artistes présentés par les Ateliers Pehouet\u{202F}: leur parcours, une sélection d’œuvres et leurs expositions, passées et à venir.",
        'eyebrow' => 'Les artistes de l’atelier',
        'hero_title' => 'Artistes',
        'lead' => 'Un parcours, des œuvres, des expositions. Chaque artiste présenté par l’atelier a sa page, pensée comme un accrochage.',
        'cta_discover' => 'Découvrir les artistes',
        'cta_exhibit' => 'Exposer avec nous',
        'list_title' => 'Les artistes présentés',
        'count' => '{0} Aucun artiste pour l’instant|{1} :count artiste|[2,*] :count artistes',
        'empty' => [
            'eyebrow' => 'Bientôt',
            'title' => 'Les premières pages se préparent',
            'text' => "L’atelier prépare les pages de ses artistes\u{202F}: biographies, œuvres choisies et expositions. Revenez bientôt, ou écrivez-nous pour en savoir plus.",
            'button' => 'Écrire à l’atelier',
        ],
        'unavailable' => [
            'eyebrow' => 'Momentanément indisponible',
            'title' => 'Les artistes reviennent très vite',
            'text' => "Les pages des artistes sont momentanément indisponibles. Le reste du site fonctionne normalement\u{202F}; revenez dans quelques minutes.",
            'button' => 'Écrire à l’atelier',
        ],
        'cta' => [
            'title' => "Vous êtes artiste\u{202F}?",
            'text' => "Présenter votre travail, exposer, imaginer un projet commun\u{202F}: parlons-en.",
            'button' => 'Écrire à l’atelier',
            'secondary' => 'Découvrir l’atelier',
        ],
    ],

    // Artist card (index grid and "Autres artistes").
    'card' => [
        'more' => 'Voir la page',
    ],

    // --- /artistes/{slug} ------------------------------------------------------------
    'show' => [
        'eyebrow' => 'Artiste',
        'admin_edit' => 'Modifier cet artiste',
        'portrait_alt' => 'Portrait de :name',
        'key_work' => 'Œuvre phare',
        'example' => "Page d’exemple\u{202F}: artiste et œuvres fictifs.",

        'actions' => [
            'works' => 'Voir les œuvres',
            'exhibitions' => 'Expositions',
            'contact' => 'Contacter l’atelier',
        ],

        'facts' => [
            'label' => 'L’artiste en bref',
            'artworks' => 'Œuvres',
            'exhibitions' => 'Expositions',
            'discipline' => 'Discipline',
            'location' => 'Basé·e à',
            'links' => 'Liens',
        ],

        'bio' => [
            'eyebrow' => 'L’artiste',
            'title' => 'Biographie',
            'sheet' => 'Fiche',
            'inquire' => 'Demander des informations',
        ],

        'works' => [
            'eyebrow' => 'Sélection',
            'title' => 'Œuvres choisies',
            'filter_label' => 'Filtrer les œuvres par disponibilité',
            'all' => 'Toutes',
            'announce' => '{count} œuvre(s) affichée(s)',
            'zoom' => 'Agrandir l’image',
            'details' => 'Notice de l’œuvre',
            'no_image' => 'Visuel à venir',
        ],

        'exhibitions' => [
            'eyebrow' => 'Actualité & parcours',
            'title' => 'Expositions',
            'now' => 'En ce moment & à venir',
            'cv' => 'Parcours',
            'more' => 'En savoir plus',
        ],

        'more' => [
            'eyebrow' => 'Découvrir',
            'title' => 'Autres artistes',
            'nav_label' => 'Naviguer entre les artistes',
            'prev' => 'Artiste précédent',
            'next' => 'Artiste suivant',
            'all' => 'Tous les artistes',
        ],

        'cta' => [
            'title' => "Une œuvre vous intéresse\u{202F}?",
            'text' => "Disponibilité, tarif ou commande\u{202F}: écrivez à l’atelier, nous vous répondrons.",
            'button' => 'Écrire à l’atelier',
            'secondary' => 'Tous les artistes',
        ],
    ],

];
