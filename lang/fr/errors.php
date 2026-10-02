<?php

// Error pages (resources/views/errors/*).
return [
    'back_home' => 'Retour à l’accueil',
    'services' => 'Voir les services',
    'contact' => 'Nous écrire',
    'retry' => 'Réessayer',
    '404' => [
        'title' => 'Œuvre introuvable',
        'text' => 'Cette page s’est détachée du triangle. Elle a peut-être été déplacée, ou n’a jamais existé.',
        'code' => 'Erreur 404',
    ],
    '419' => [
        'title' => 'Session expirée',
        'text' => 'La page est restée ouverte un peu trop longtemps. Rechargez-la et renvoyez votre formulaire.',
        'code' => 'Erreur 419',
    ],
    '429' => [
        'title' => 'Un peu trop vite',
        'text' => 'Vous avez fait beaucoup de demandes en peu de temps. Patientez une minute avant de réessayer.',
        'code' => 'Erreur 429',
    ],
    '500' => [
        'title' => 'L’atelier est en désordre',
        'text' => 'Une erreur inattendue s’est produite. Nous rangeons nos pinceaux : réessayez dans un instant.',
        'code' => 'Erreur 500',
    ],
    '503' => [
        'title' => 'L’atelier se refait une beauté',
        'text' => 'Le site est en maintenance pour quelques instants. Merci de votre patience.',
        'code' => 'Maintenance',
    ],
];
