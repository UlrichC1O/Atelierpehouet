<?php

// Service n°19 — Sérigraphie & impression (docs/ARCHITECTURE.md §4).
// French is the main language; English is its translation. Edit the copy here.

return [
    'slug' => 'serigraphie-impression',
    'order' => 19,
    'category' => 'image',
    'accent' => 'yellow',
    'icon' => 'print',
    'art_style' => 'pehouet',
    'scene' => 'serigraphie-impression',
    'fr' => [
        'title' => 'Sérigraphie & impression',
        'short' => 'Tee-shirts, sacs, affiches et bannières sérigraphiés couleur par couleur pour vos équipes et vos événements.',
        'tagline' => 'Une couleur après l’autre, jusqu’à l’image complète.',
        'intro' => 'La sérigraphie dépose l’encre à travers un écran, une couleur après l’autre : c’est une technique généreuse, aux aplats francs, parfaite pour notre univers graphique. L’atelier imprime tee-shirts, sweats, sacs en toile, affiches et petites séries pour les associations, les équipes, les événements, les commerces et les créateurs.',
        'body' => [
            'Nous préparons votre visuel pour l’impression : séparation des couleurs, choix des encres, emplacement sur le support. Une épreuve ou un échantillon vous est présenté avant le lancement de la série. Les encres sont choisies selon le support pour une bonne tenue au lavage.',
            'La sérigraphie est aussi une belle activité à partager. Nous animons des ateliers où chacun imprime son propre sac ou son affiche, découvre le passage de la racle et repart avec une pièce unique, imprimée de ses mains.',
        ],
        'features' => [
            ['title' => 'Textile', 'text' => 'Tee-shirts, sweats, tabliers et sacs en toile imprimés.'],
            ['title' => 'Affiches', 'text' => 'Des affiches aux couleurs franches pour vos événements.'],
            ['title' => 'Préparation des fichiers', 'text' => 'Séparation des couleurs et calage de votre visuel.'],
            ['title' => 'Épreuve avant série', 'text' => 'Un échantillon à valider avant l’impression complète.'],
            ['title' => 'Petites séries', 'text' => 'Des quantités adaptées aux associations et aux événements.'],
            ['title' => 'Ateliers d’impression', 'text' => 'Imprimer soi-même son sac ou son affiche, accompagné.'],
        ],
        'process' => [
            ['title' => 'Le visuel', 'text' => 'Votre image ou une création de l’atelier, préparée pour l’écran.'],
            ['title' => 'L’écran', 'text' => 'Fabrication des écrans, un par couleur, et choix des encres.'],
            ['title' => 'L’impression', 'text' => 'Passage de la racle couleur par couleur, avec repérage précis.'],
            ['title' => 'Le séchage & la remise', 'text' => 'Fixation des encres, contrôle qualité et livraison.'],
        ],
        'ideal_for' => [
            'Associations et clubs',
            'Événements et festivals',
            'Commerces et créateurs',
            'Écoles et ateliers de groupe',
        ],
        'faq' => [
            [
                'q' => 'Quelle quantité minimale ?',
                'a' => 'La sérigraphie est rentable dès quelques dizaines de pièces. Pour une ou deux pièces uniques, la peinture textile de l’atelier est plus adaptée.',
            ],
            [
                'q' => 'Combien de couleurs peut-on imprimer ?',
                'a' => 'Chaque couleur demande un écran. Nous vous conseillons pour obtenir le meilleur rendu avec le bon nombre de couleurs.',
            ],
            [
                'q' => 'Les impressions résistent-elles au lavage ?',
                'a' => 'Oui, avec des encres adaptées et correctement fixées. Nous vous indiquons la température et les précautions de lavage.',
            ],
            [
                'q' => 'Pouvez-vous fournir les tee-shirts ?',
                'a' => 'Oui, nous pouvons proposer des supports textiles ou imprimer sur ceux que vous fournissez, après vérification de leur composition.',
            ],
        ],
        'scene_alt' => 'Sous un cadre de sérigraphie, la racle passe et dépose à chaque passage une couleur — bleu, jaune, rouge — qui reconstitue le triangle.',
        'meta_description' => 'Sérigraphie et impression : tee-shirts, sacs, affiches et petites séries pour associations, événements et créateurs, et ateliers d’impression. Devis gratuit.',
    ],
    'en' => [
        'title' => 'Screen printing',
        'short' => 'T-shirts, bags, posters and banners screen-printed colour by colour for your teams and events.',
        'tagline' => 'One colour after another, until the image is complete.',
        'intro' => 'Screen printing pushes ink through a screen, one colour at a time: a generous technique with bold flat colours, perfect for our graphic world. The atelier prints T-shirts, sweatshirts, tote bags, posters and short runs for associations, teams, events, shops and makers.',
        'body' => [
            'We prepare your visual for printing: colour separation, choice of inks and placement on the item. A proof or a sample is shown to you before the run starts. Inks are chosen for each material so that prints hold up well in the wash.',
            'Screen printing is also a wonderful activity to share. We run workshops where everyone prints their own bag or poster, discovers the pull of the squeegee and leaves with a unique piece printed by their own hands.',
        ],
        'features' => [
            ['title' => 'Textiles', 'text' => 'Printed T-shirts, sweatshirts, aprons and tote bags.'],
            ['title' => 'Posters', 'text' => 'Posters with bold colours for your events.'],
            ['title' => 'File preparation', 'text' => 'Colour separation and registration of your visual.'],
            ['title' => 'Proof before the run', 'text' => 'A sample to approve before the full print.'],
            ['title' => 'Short runs', 'text' => 'Quantities suited to associations and events.'],
            ['title' => 'Printing workshops', 'text' => 'Print your own bag or poster, with guidance.'],
        ],
        'process' => [
            ['title' => 'The visual', 'text' => 'Your image or an atelier creation, prepared for the screen.'],
            ['title' => 'The screens', 'text' => 'One screen per colour, and the choice of inks.'],
            ['title' => 'The printing', 'text' => 'The squeegee pulled colour by colour, in precise registration.'],
            ['title' => 'Curing & delivery', 'text' => 'Ink curing, quality check and delivery.'],
        ],
        'ideal_for' => ['Associations and clubs', 'Events and festivals', 'Shops and makers', 'Schools and group workshops'],
        'faq' => [
            [
                'q' => 'What is the minimum quantity?',
                'a' => 'Screen printing makes sense from a few dozen pieces. For one or two unique pieces, the atelier’s fabric painting is better suited.',
            ],
            ['q' => 'How many colours can be printed?', 'a' => 'Each colour needs its own screen. We advise you on getting the best result with the right number of colours.'],
            ['q' => 'Do the prints survive washing?', 'a' => 'Yes, with suitable, properly cured inks. We tell you the right temperature and washing precautions.'],
            ['q' => 'Can you supply the T-shirts?', 'a' => 'Yes, we can source garments or print on items you provide, after checking their composition.'],
        ],
        'scene_alt' => 'Under a screen-printing frame, the squeegee passes and each pass adds a colour — blue, yellow, red — that rebuilds the triangle.',
        'meta_description' => 'Screen printing: T-shirts, bags, posters and short runs for associations, events and makers, plus hands-on printing workshops. Free quote.',
    ],
];
