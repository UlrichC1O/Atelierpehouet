<?php

// Service n°1 — Peinture murale & fresques (docs/ARCHITECTURE.md §4).
// French is the main language; English is its translation. Edit the copy here.

return [
    'slug' => 'peinture-murale',
    'order' => 1,
    'category' => 'peinture',
    'accent' => 'red',
    'icon' => 'roller',
    'art_style' => 'mondrian',
    'scene' => 'peinture-murale',
    'fr' => [
        'title' => 'Peinture murale & fresques',
        'short' => 'Des murs qui racontent votre histoire : fresques intérieures et extérieures, peintes à la main.',
        'tagline' => 'Chaque mur peut devenir une fenêtre sur ce qui nous rassemble.',
        'intro' => 'Un mur nu est une page blanche à l’échelle d’une rue. L’atelier conçoit et peint des fresques intérieures et extérieures pour les écoles, les commerces, les associations, les lieux de culte et les particuliers. Du premier croquis à la dernière couche de vernis, nous imaginons avec vous une image qui parle aux passants, embellit le quartier et traverse les saisons.',
        'body' => [
            'Chaque projet commence par le lieu : sa lumière, ses usages, les personnes qui le traversent. Nous étudions le support (béton, crépi, brique, bois, plâtre), proposons une maquette en couleurs et ajustons la composition jusqu’à ce qu’elle vous ressemble. Les motifs peuvent raconter votre histoire, vos valeurs, votre activité ou les visages de votre communauté.',
            'Pour les fresques participatives, nous invitons enfants, voisins ou équipes à peindre certaines zones à nos côtés, sous la conduite de l’atelier. Le résultat garde la tenue d’une œuvre professionnelle tout en portant la trace de toutes les mains qui l’ont faite. Une fois terminée, la fresque reçoit un vernis adapté à l’intérieur ou à l’extérieur.',
        ],
        'features' => [
            ['title' => 'Étude du support', 'text' => 'Diagnostic du mur, nettoyage, enduits et sous-couches pour une peinture qui tient dans la durée.'],
            ['title' => 'Maquette en couleurs', 'text' => 'Une esquisse à l’échelle pour valider la composition avant le premier coup de pinceau.'],
            ['title' => 'Intérieur & extérieur', 'text' => 'Des peintures choisies pour le soleil, la pluie, l’humidité ou les passages fréquents.'],
            ['title' => 'Fresque participative', 'text' => 'Des séances encadrées où votre communauté peint une partie de l’œuvre avec l’atelier.'],
            ['title' => 'Grandes surfaces', 'text' => 'Façades, préaux, halls, murs d’enceinte : un report précis du dessin, même à grande échelle.'],
            ['title' => 'Vernis de protection', 'text' => 'Une finition anti-UV et, si besoin, anti-graffiti pour garder des couleurs éclatantes.'],
        ],
        'process' => [
            ['title' => 'Rencontre & repérage', 'text' => 'Nous visitons le lieu, mesurons le mur et écoutons ce que vous voulez transmettre.'],
            ['title' => 'Esquisse & maquette', 'text' => 'Une première composition en couleurs, retravaillée avec vous jusqu’à validation.'],
            ['title' => 'Préparation & peinture', 'text' => 'Préparation du support, report du dessin puis peinture, étape par étape.'],
            ['title' => 'Vernis & inauguration', 'text' => 'Finitions, protection et, si vous le souhaitez, un moment pour dévoiler l’œuvre.'],
        ],
        'ideal_for' => [
            'Écoles et centres de loisirs',
            'Commerces, restaurants et bureaux',
            'Associations, mairies et lieux de culte',
            'Particuliers : façades, jardins, chambres',
        ],
        'faq' => [
            [
                'q' => 'Combien de temps faut-il pour réaliser une fresque ?',
                'a' => 'Tout dépend de la surface, du support et du niveau de détail. Après la visite du lieu, nous vous proposons un calendrier précis pour la préparation, la peinture et le séchage.',
            ],
            [
                'q' => 'La fresque résiste-t-elle aux intempéries ?',
                'a' => 'Oui, lorsque le support est bien préparé et que l’on emploie des peintures extérieures. Un vernis de protection complète le travail et nous vous expliquons comment l’entretenir.',
            ],
            [
                'q' => 'Pouvons-nous participer à la réalisation ?',
                'a' => 'Avec plaisir. Nous organisons des séances encadrées où enfants, voisins ou collègues peignent certaines parties de l’œuvre. C’est souvent le moment le plus fort du projet.',
            ],
            [
                'q' => 'Comment le devis est-il établi ?',
                'a' => 'Le devis est gratuit et personnalisé. Il tient compte de la surface, de l’accès (échafaudage, nacelle), de la complexité du dessin et des fournitures nécessaires.',
            ],
        ],
        'scene_alt' => 'Un rouleau peint des bandes bleues, jaunes et rouges sur un mur noir, jusqu’à faire apparaître un grand triangle.',
        'meta_description' => 'Fresques et peintures murales sur mesure, intérieures et extérieures, pour écoles, commerces et associations : maquette, peinture à la main, fresques participatives.',
    ],
    'en' => [
        'title' => 'Murals & frescoes',
        'short' => 'Walls that tell your story: indoor and outdoor murals, designed with you and painted by hand.',
        'tagline' => 'Any wall can become a window onto what brings us together.',
        'intro' => 'A bare wall is a blank page the size of a street. The atelier designs and paints indoor and outdoor murals for schools, shops, associations, places of worship and private homes. From the first sketch to the final coat of varnish, we imagine with you an image that speaks to passers-by, brightens the neighbourhood and weathers the seasons.',
        'body' => [
            'Every project starts with the place: its light, its uses, the people who walk past it. We assess the surface (concrete, render, brick, wood, plaster), present a colour mock-up and refine the composition until it feels like yours. The imagery can tell your story, your values, your trade or the faces of your community.',
            'For participatory murals, we invite children, neighbours or teams to paint some areas alongside us, guided by the atelier. The result keeps the finish of a professional artwork while carrying the mark of every hand that made it. Once complete, the mural is sealed with a varnish suited to indoor or outdoor conditions.',
        ],
        'features' => [
            ['title' => 'Surface assessment', 'text' => 'Wall diagnosis, cleaning, fillers and primers so the paint lasts for years.'],
            ['title' => 'Colour mock-up', 'text' => 'A scaled sketch to approve the composition before the first brushstroke.'],
            ['title' => 'Indoor & outdoor', 'text' => 'Paints chosen for sun, rain, humidity or busy corridors.'],
            ['title' => 'Participatory mural', 'text' => 'Guided sessions where your community paints part of the artwork with the atelier.'],
            ['title' => 'Large surfaces', 'text' => 'Façades, playground walls, halls and boundary walls: accurate transfer even at a large scale.'],
            ['title' => 'Protective varnish', 'text' => 'A UV-resistant and, if needed, anti-graffiti finish to keep colours vivid.'],
        ],
        'process' => [
            ['title' => 'Meeting & site visit', 'text' => 'We visit the place, measure the wall and listen to what you want to say.'],
            ['title' => 'Sketch & mock-up', 'text' => 'A first colour composition, reworked with you until it is approved.'],
            ['title' => 'Preparation & painting', 'text' => 'Surface preparation, transfer of the drawing, then painting, step by step.'],
            ['title' => 'Varnish & unveiling', 'text' => 'Finishing touches, protection and, if you wish, a moment to unveil the work.'],
        ],
        'ideal_for' => [
            'Schools and youth centres',
            'Shops, restaurants and offices',
            'Associations, town halls and places of worship',
            'Homes: façades, gardens, bedrooms',
        ],
        'faq' => [
            [
                'q' => 'How long does a mural take?',
                'a' => 'It depends on the surface, the wall and the level of detail. After visiting the site, we give you a precise schedule for preparation, painting and drying.',
            ],
            [
                'q' => 'Will the mural withstand the weather?',
                'a' => 'Yes, when the surface is properly prepared and exterior paints are used. A protective varnish completes the work and we explain how to look after it.',
            ],
            [
                'q' => 'Can we take part in painting it?',
                'a' => 'Gladly. We run guided sessions where children, neighbours or colleagues paint parts of the artwork. It is often the most memorable moment of the project.',
            ],
            [
                'q' => 'How is the quote prepared?',
                'a' => 'Quotes are free and tailored. They take into account the surface, access (scaffolding, lift), the complexity of the design and the materials needed.',
            ],
        ],
        'scene_alt' => 'A roller paints blue, yellow and red bands across a black wall until a great triangle appears.',
        'meta_description' => 'Custom indoor and outdoor murals for schools, shops and associations: colour mock-ups, hand painting and participatory murals by Ateliers Pehouet.',
    ],
];
