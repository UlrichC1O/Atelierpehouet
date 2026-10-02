<?php

/*
|--------------------------------------------------------------------------
| E-mails envoyés par le site (B · App\Mail)
|--------------------------------------------------------------------------
*/

return [

    'contact' => [
        'subject' => 'Nouvelle demande — :name',
        'preheader' => 'Une nouvelle demande vient d’arriver par le formulaire du site.',
        'tagline' => 'L’art au service de la communauté',
        'eyebrow' => 'Formulaire de contact',
        'heading' => 'Nouvelle demande de devis',
        'intro' => ':name vous a écrit depuis le site le :date.',
        'line' => ':label : :value',
        'fields' => [
            'name' => 'Nom',
            'email' => 'E-mail',
            'phone' => 'Téléphone',
            'service' => 'Service',
            'budget' => 'Budget',
            'locale' => 'Langue du message',
            'message' => 'Message',
        ],
        'not_specified' => 'Non précisé',
        'reply' => 'Répondre à :name',
        'footer' => 'Message envoyé depuis le formulaire de contact de :site. Répondez simplement à cet e-mail pour écrire directement à :name.',
        'failed' => 'Votre message n’a pas pu être envoyé pour le moment. Merci de réessayer un peu plus tard.',
        'budgets' => [
            'small' => 'Petit budget',
            'medium' => 'Budget intermédiaire',
            'large' => 'Budget important',
            'unsure' => 'À définir ensemble',
        ],
    ],

];
