<?php

// Home page (resources/views/pages/home.blade.php).
return [
    'meta' => 'Ateliers Pehouet, atelier d’art au service de la communauté : fresques, tableaux, portraits, design, enseignes, sculpture, décors, cours et projets participatifs.',
    'hero' => [
        'heading' => 'Ateliers Pehouet — L’art au service de la communauté',
        'prefix' => 'L’atelier de',
        'words' => ['la fresque', 'la sculpture', 'la couleur', 'l’enseigne', 'la mosaïque', 'la scène', 'la transmission', 'la communauté'],
        'cta_services' => 'Découvrir nos services',
        'cta_create' => 'Créer mon œuvre',
        'scroll' => 'Faire défiler',
    ],
    'manifesto' => [
        'eyebrow' => 'Manifeste',
        'statement' => 'Nous croyons qu’un mur, une place, une salle de classe ou une fête peuvent devenir des œuvres qui rassemblent.',
        'text' => 'Notre logo le dit en cinq couleurs. Voici comment nous le lisons.',
        'colours' => [
            'blue' => ['name' => 'Bleu', 'meaning' => 'La confiance et l’écoute : chaque projet commence par vous entendre.'],
            'yellow' => ['name' => 'Jaune', 'meaning' => 'La lumière et l’idée : l’étincelle qui transforme un lieu.'],
            'red' => ['name' => 'Rouge', 'meaning' => 'L’énergie du geste : peindre, tailler, assembler, avec passion.'],
            'white' => ['name' => 'Blanc', 'meaning' => 'Les joints qui relient : l’art comme lien entre les personnes.'],
            'black' => ['name' => 'Noir', 'meaning' => 'Le socle et la toile : l’espace d’où naît toute couleur.'],
        ],
    ],
    'services' => [
        'eyebrow' => 'Savoir-faire',
        'title' => ':count services, un même regard',
        'lead' => 'Peinture, design, matière, espaces, image et transmission : choisissez une famille ou parcourez tout le catalogue.',
        'all' => 'Voir tous les services',
        'family_count' => '{1} :count service|[2,*] :count services',
    ],
    'process' => [
        'eyebrow' => 'Méthode',
        'title' => 'Du premier mot à l’œuvre dévoilée',
        'steps' => [
            ['title' => 'Écouter', 'text' => 'Votre lieu, votre histoire, vos envies.'],
            ['title' => 'Esquisser', 'text' => 'Croquis, maquettes et palettes à valider.'],
            ['title' => 'Créer', 'text' => 'Le geste de l’atelier, seul ou avec vous.'],
            ['title' => 'Célébrer', 'text' => 'L’œuvre dévoilée et partagée.'],
        ],
    ],
    'generator' => [
        'eyebrow' => 'Atelier numérique',
        'title' => 'Un mot, une œuvre',
        'text' => 'Écrivez un prénom, une rue, une date : notre moteur génératif, écrit en Python, compose une œuvre unique dans les couleurs du logo.',
        'cta' => 'Essayer maintenant',
        'alt' => 'Composition générative dans le style :style',
    ],
    'gallery' => [
        'eyebrow' => 'Galerie',
        'title' => 'Les couleurs en liberté',
        'text' => 'Triangles, vitraux, mosaïques, éclats : une collection de compositions nées de notre moteur.',
        'cta' => 'Entrer dans la galerie',
    ],
    'community' => [
        'eyebrow' => 'Communauté',
        'title' => 'L’art se fait ensemble',
        'text' => 'Fresques de quartier, ateliers pour enfants, décors de fêtes, projets intergénérationnels : l’atelier met son savoir-faire au service des lieux et des personnes qui les font vivre.',
        'quote' => 'Une œuvre partagée appartient à tous ceux qui l’ont regardée naître.',
        'cta' => 'Découvrir nos projets communautaires',
    ],
    'cta' => [
        'title' => 'Créons ensemble',
        'text' => 'Un mur à réveiller, un lieu à habiller, un atelier à partager : parlons de votre projet.',
        'button' => 'Demander un devis',
        'secondary' => 'Voir les services',
    ],
];
