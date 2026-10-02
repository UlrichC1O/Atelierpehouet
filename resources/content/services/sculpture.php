<?php

// Service n°10 — Sculpture & installations (docs/ARCHITECTURE.md §4).
// French is the main language; English is its translation. Edit the copy here.

return [
    'slug' => 'sculpture',
    'order' => 10,
    'category' => 'matiere',
    'accent' => 'blue',
    'icon' => 'chisel',
    'art_style' => 'prisme',
    'scene' => 'sculpture',
    'fr' => [
        'title' => 'Sculpture & installations',
        'short' => 'Sculptures, trophées et installations en volume, du petit objet à la pièce monumentale.',
        'tagline' => 'Donner du volume à une idée, et une place dans l’espace.',
        'intro' => 'La sculpture fait entrer l’art dans l’espace où l’on vit. L’atelier crée des pièces en bois, en métal, en résine ou en matériaux de récupération : sculptures décoratives, trophées et distinctions, installations pour une place, une cour d’école, une vitrine ou un événement. Des formes taillées dans l’esprit du triangle, solides et lumineuses.',
        'body' => [
            'Nous étudions le lieu d’accueil, la circulation autour de l’œuvre, la lumière et la sécurité. Une maquette ou une modélisation vous permet de visualiser le volume avant la fabrication. Les matériaux sont choisis selon l’usage : intérieur, extérieur, manipulation par le public ou installation temporaire.',
            'Les installations participatives donnent une seconde vie aux objets : bouteilles, bois flotté, métal ou plastique collectés avec les habitants deviennent une œuvre commune. C’est une manière concrète de parler de récupération, de patrimoine ou de mémoire collective à travers une création qui reste.',
        ],
        'features' => [
            ['title' => 'Matériaux variés', 'text' => 'Bois, métal, résine, plâtre ou matériaux récupérés, selon le projet.'],
            ['title' => 'Maquette préalable', 'text' => 'Une maquette ou une modélisation pour visualiser le volume.'],
            ['title' => 'Trophées & distinctions', 'text' => 'Des pièces uniques pour récompenser, célébrer ou commémorer.'],
            ['title' => 'Installations', 'text' => 'Des œuvres pour places, cours, vitrines, halls et événements.'],
            ['title' => 'Récup & réemploi', 'text' => 'Des créations collectives à partir d’objets collectés.'],
            ['title' => 'Sécurité & tenue', 'text' => 'Fixations, stabilité et finitions adaptées au public et au lieu.'],
        ],
        'process' => [
            ['title' => 'Le lieu', 'text' => 'Visite, dimensions, lumière, usages et contraintes de sécurité.'],
            ['title' => 'La maquette', 'text' => 'Esquisses et maquette pour valider forme, échelle et matériaux.'],
            ['title' => 'La fabrication', 'text' => 'Taille, assemblage, soudure ou modelage, puis finitions.'],
            ['title' => 'L’installation', 'text' => 'Transport, pose, fixation et présentation de l’œuvre.'],
        ],
        'ideal_for' => [
            'Places, parcs et espaces publics',
            'Écoles et centres culturels',
            'Entreprises et remises de prix',
            'Événements et vitrines',
        ],
        'faq' => [
            [
                'q' => 'Une sculpture peut-elle rester dehors ?',
                'a' => 'Oui, avec des matériaux et des traitements adaptés à l’extérieur. Nous vous indiquons l’entretien à prévoir selon le matériau retenu.',
            ],
            [
                'q' => 'Pouvez-vous réaliser des trophées en série ?',
                'a' => 'Oui, pour de petites séries. Chaque pièce garde une finition artisanale ; nous pouvons personnaliser chaque exemplaire avec un nom ou une date.',
            ],
            [
                'q' => 'Comment se passe une installation participative ?',
                'a' => 'Nous organisons la collecte des objets puis des ateliers d’assemblage encadrés. L’atelier assure la conception, la structure et la sécurité de l’œuvre.',
            ],
            [
                'q' => 'Qui s’occupe de l’installation sur place ?',
                'a' => 'Nous prenons en charge la pose ou travaillons avec vos équipes techniques. Les conditions de transport et de montage sont détaillées dans le devis.',
            ],
        ],
        'scene_alt' => 'Une pyramide aux faces bleue, jaune et rouge tourne lentement sur un socle pendant qu’un ciseau en fait jaillir des éclats.',
        'meta_description' => 'Sculptures, trophées et installations en bois, métal, résine ou récupération, pour espaces publics, écoles et événements. Ateliers Pehouet, devis gratuit.',
    ],
    'en' => [
        'title' => 'Sculpture & installations',
        'short' => 'Sculptures, trophies and installations, from small objects to monumental pieces.',
        'tagline' => 'Giving an idea volume, and a place in space.',
        'intro' => 'Sculpture brings art into the spaces where we live. The atelier creates pieces in wood, metal, resin or reclaimed materials: decorative sculptures, trophies and awards, installations for a square, a schoolyard, a shop window or an event. Forms carved in the spirit of the triangle, solid and luminous.',
        'body' => [
            'We study the host site, the movement around the work, the light and safety. A model or 3D view lets you picture the volume before fabrication. Materials are chosen according to use: indoors, outdoors, handled by the public or installed temporarily.',
            'Participatory installations give objects a second life: bottles, driftwood, metal or plastic collected with residents become a shared artwork. It is a concrete way to talk about reuse, heritage or collective memory through a creation that stays.',
        ],
        'features' => [
            ['title' => 'Varied materials', 'text' => 'Wood, metal, resin, plaster or reclaimed materials, depending on the project.'],
            ['title' => 'Model first', 'text' => 'A maquette or 3D view to picture the volume.'],
            ['title' => 'Trophies & awards', 'text' => 'Unique pieces to reward, celebrate or commemorate.'],
            ['title' => 'Installations', 'text' => 'Works for squares, courtyards, shop windows, halls and events.'],
            ['title' => 'Reuse & upcycling', 'text' => 'Collective creations made from collected objects.'],
            ['title' => 'Safety & durability', 'text' => 'Fixings, stability and finishes suited to the public and the site.'],
        ],
        'process' => [
            ['title' => 'The site', 'text' => 'Visit, dimensions, light, uses and safety constraints.'],
            ['title' => 'The model', 'text' => 'Sketches and a maquette to approve form, scale and materials.'],
            ['title' => 'The fabrication', 'text' => 'Carving, assembling, welding or modelling, then finishing.'],
            ['title' => 'The installation', 'text' => 'Transport, placement, fixing and presentation of the work.'],
        ],
        'ideal_for' => [
            'Squares, parks and public spaces',
            'Schools and cultural centres',
            'Companies and award ceremonies',
            'Events and shop windows',
        ],
        'faq' => [
            ['q' => 'Can a sculpture stay outdoors?', 'a' => 'Yes, with materials and treatments suited to the outdoors. We explain the care required for the chosen material.'],
            ['q' => 'Can you make trophies in series?', 'a' => 'Yes, in small series. Every piece keeps a handmade finish, and we can personalise each one with a name or a date.'],
            [
                'q' => 'How does a participatory installation work?',
                'a' => 'We organise the collection of objects, then supervised assembly workshops. The atelier handles the design, the structure and the safety of the work.',
            ],
            [
                'q' => 'Who installs the work on site?',
                'a' => 'We take care of installation or work with your technical teams. Transport and assembly conditions are detailed in the quote.',
            ],
        ],
        'scene_alt' => 'A pyramid with blue, yellow and red faces slowly turns on a plinth while a chisel sends chips flying.',
        'meta_description' => 'Sculptures, trophies and installations in wood, metal, resin or reclaimed materials for public spaces, schools and events. Ateliers Pehouet, free quote.',
    ],
];
