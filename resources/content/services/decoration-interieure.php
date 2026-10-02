<?php

// Service n°15 — Décoration intérieure (docs/ARCHITECTURE.md §4).
// French is the main language; English is its translation. Edit the copy here.

return [
    'slug' => 'decoration-interieure',
    'order' => 15,
    'category' => 'espaces',
    'accent' => 'blue',
    'icon' => 'sofa',
    'art_style' => 'tissage',
    'scene' => 'decoration-interieure',
    'fr' => [
        'title' => 'Décoration intérieure',
        'short' => 'Couleurs, motifs peints, œuvres et objets choisis pour donner une âme à vos espaces.',
        'tagline' => 'Habiter un lieu, c’est aussi habiter ses couleurs.',
        'intro' => 'Un intérieur réussi raconte qui l’habite. L’atelier conçoit des ambiances complètes pour les maisons, les bureaux, les commerces, les salles d’accueil et les lieux de culte : palettes de couleurs, murs géométriques, motifs peints, meubles relookés et œuvres créées pour l’endroit. Une décoration artistique, cohérente et à votre image.',
        'body' => [
            'Nous visitons l’espace, observons la lumière à différents moments de la journée et écoutons vos usages. Nous vous proposons ensuite une planche d’ambiance : couleurs, matières, motifs, emplacement des œuvres. Vous pouvez tout confier à l’atelier ou réaliser certaines étapes vous-même avec nos indications.',
            'Les murs géométriques inspirés de notre triangle, les soubassements colorés ou un trompe-l’œil discret transforment une pièce sans gros travaux. Un meuble ancien repeint ou une série de tableaux pensés pour un couloir suffisent parfois à changer toute l’atmosphère.',
        ],
        'features' => [
            ['title' => 'Planche d’ambiance', 'text' => 'Couleurs, matières et motifs réunis pour visualiser le projet.'],
            ['title' => 'Murs géométriques', 'text' => 'Des aplats et des triangles peints qui structurent l’espace.'],
            ['title' => 'Trompe-l’œil', 'text' => 'Une ouverture, une étagère ou un paysage peints sur un mur.'],
            ['title' => 'Meubles relookés', 'text' => 'Des meubles repeints et décorés qui reprennent vie.'],
            ['title' => 'Œuvres pour le lieu', 'text' => 'Tableaux et objets créés pour s’accorder à la pièce.'],
            ['title' => 'Accompagnement', 'text' => 'Tout réalisé par l’atelier ou avec vos propres mains, guidées.'],
        ],
        'process' => [
            ['title' => 'La visite', 'text' => 'Lumière, usages, envies et contraintes de chaque pièce.'],
            ['title' => 'L’ambiance', 'text' => 'Une planche de couleurs, matières et motifs à valider.'],
            ['title' => 'La réalisation', 'text' => 'Peinture, décors, meubles et œuvres mis en place.'],
            ['title' => 'Les derniers détails', 'text' => 'Accrochage, lumière et ajustements finaux.'],
        ],
        'ideal_for' => [
            'Maisons et appartements',
            'Bureaux et espaces d’accueil',
            'Restaurants, cafés et boutiques',
            'Lieux de culte et salles communautaires',
        ],
        'faq' => [
            [
                'q' => 'Faut-il refaire toute la pièce ?',
                'a' => 'Non. Un mur, un meuble ou une œuvre bien choisis suffisent souvent. Nous adaptons le projet à votre budget et à vos priorités.',
            ],
            [
                'q' => 'Pouvons-nous peindre nous-mêmes certaines parties ?',
                'a' => 'Oui. Nous préparons les plans, les tracés et les teintes, et nous vous montrons les gestes. L’atelier réalise les parties les plus délicates.',
            ],
            [
                'q' => 'Les couleurs choisies seront-elles fidèles ?',
                'a' => 'Nous testons les teintes directement sur vos murs, car la lumière de chaque pièce change la perception des couleurs.',
            ],
            [
                'q' => 'Intervenez-vous dans les locaux professionnels ?',
                'a' => 'Oui, en organisant les travaux pour limiter la gêne : interventions par zones, horaires adaptés et nettoyage soigné.',
            ],
        ],
        'scene_alt' => 'Dans une pièce en perspective, le mur change de couleur, des cadres glissent jusqu’à leur place et une lampe s’allume.',
        'meta_description' => 'Décoration intérieure artistique : palettes de couleurs, murs géométriques, trompe-l’œil, meubles relookés et œuvres créées pour vos espaces. Devis gratuit.',
    ],
    'en' => [
        'title' => 'Interior decoration',
        'short' => 'Colours, painted patterns, artworks and objects chosen to give your spaces a soul.',
        'tagline' => 'To live in a place is also to live in its colours.',
        'intro' => 'A successful interior tells who lives there. The atelier designs complete atmospheres for homes, offices, shops, reception areas and places of worship: colour palettes, geometric walls, painted patterns, revamped furniture and artworks created for the place. Artistic, coherent decoration that looks like you.',
        'body' => [
            'We visit the space, watch the light at different times of day and listen to how you use it. We then present a mood board: colours, materials, patterns and where the artworks go. You can entrust everything to the atelier or carry out some steps yourself with our guidance.',
            'Geometric walls inspired by our triangle, coloured dados or a subtle trompe-l’œil transform a room without major works. A repainted piece of furniture or a series of paintings designed for a corridor is sometimes enough to change the whole atmosphere.',
        ],
        'features' => [
            ['title' => 'Mood board', 'text' => 'Colours, materials and patterns gathered to picture the project.'],
            ['title' => 'Geometric walls', 'text' => 'Painted fields and triangles that structure the space.'],
            ['title' => 'Trompe-l’œil', 'text' => 'An opening, a shelf or a landscape painted on a wall.'],
            ['title' => 'Revamped furniture', 'text' => 'Furniture repainted and decorated back to life.'],
            ['title' => 'Works for the place', 'text' => 'Paintings and objects created to suit the room.'],
            ['title' => 'Guidance', 'text' => 'Done entirely by the atelier, or by your own hands, guided.'],
        ],
        'process' => [
            ['title' => 'The visit', 'text' => 'Light, uses, wishes and the constraints of each room.'],
            ['title' => 'The atmosphere', 'text' => 'A board of colours, materials and patterns to approve.'],
            ['title' => 'The making', 'text' => 'Painting, decoration, furniture and artworks put in place.'],
            ['title' => 'The final details', 'text' => 'Hanging, lighting and final adjustments.'],
        ],
        'ideal_for' => [
            'Houses and flats',
            'Offices and reception areas',
            'Restaurants, cafés and shops',
            'Places of worship and community halls',
        ],
        'faq' => [
            [
                'q' => 'Do we have to redo the whole room?',
                'a' => 'No. A well-chosen wall, piece of furniture or artwork is often enough. We adapt the project to your budget and priorities.',
            ],
            [
                'q' => 'Can we paint some parts ourselves?',
                'a' => 'Yes. We prepare the plans, the outlines and the colours, and show you the techniques. The atelier handles the most delicate parts.',
            ],
            ['q' => 'Will the chosen colours look right?', 'a' => 'We test the shades directly on your walls, because the light in each room changes how colours look.'],
            ['q' => 'Do you work in business premises?', 'a' => 'Yes, organising the work to limit disruption: zone by zone, at suitable hours and with careful cleaning.'],
        ],
        'scene_alt' => 'In a room drawn in perspective, the wall changes colour, frames slide into place and a lamp switches on.',
        'meta_description' => 'Artistic interior decoration: colour palettes, geometric walls, trompe-l’œil, revamped furniture and artworks created for your spaces. Free quote.',
    ],
];
