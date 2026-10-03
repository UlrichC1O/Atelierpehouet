<?php

// Error pages (resources/views/errors/*).
return [
    'back_home' => 'Retour à l’accueil',
    'services' => 'Voir les services',
    'contact' => 'Nous écrire',
    'retry' => 'Réessayer',
    '401' => [
        'title' => 'Connexion requise',
        'text' => 'Cette page est réservée. Connectez-vous pour y accéder.',
        'code' => 'Erreur 401',
    ],
    '402' => [
        'title' => 'Accès indisponible',
        'text' => 'Cette page n’est pas accessible pour le moment.',
        'code' => 'Erreur 402',
    ],
    '403' => [
        'title' => 'Accès refusé',
        'text' => 'Vous n’avez pas l’autorisation de voir cette page.',
        'code' => 'Erreur 403',
    ],
    // Any other client error (400, 405, 410, 413…): the code shows the actual status.
    '4xx' => [
        'title' => 'Demande impossible',
        'text' => 'Le lien est peut-être incomplet, ou la page a changé. Revenez à l’accueil ou écrivez-nous.',
        'code' => 'Erreur :code',
    ],
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
