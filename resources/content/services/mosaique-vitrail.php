<?php

// Service n°12 — Mosaïque & vitrail (docs/ARCHITECTURE.md §4).
// French is the main language; English is its translation. Edit the copy here.

return [
    'slug' => 'mosaique-vitrail',
    'order' => 12,
    'category' => 'matiere',
    'accent' => 'yellow',
    'icon' => 'mosaic',
    'art_style' => 'vitrail',
    'scene' => 'mosaique-vitrail',
    'fr' => [
        'title' => 'Mosaïque & vitrail',
        'short' => 'Des tesselles et du verre coloré pour faire chanter la lumière sur vos murs et vos fenêtres.',
        'tagline' => 'Mille fragments, une seule lumière.',
        'intro' => 'La mosaïque et le vitrail partagent un même secret : assembler des fragments pour créer une image plus grande qu’eux. L’atelier réalise des mosaïques murales, des sols et tables décorés, des fresques en tesselles avec les habitants, ainsi que des vitraux et des décors de verre peint pour les maisons, les écoles et les lieux de culte.',
        'body' => [
            'Le dessin est pensé dès le départ pour la technique : découpe des tesselles, joints, résistance au gel ou à l’humidité pour la mosaïque ; réseau de plomb, transparence et orientation de la lumière pour le vitrail. Nous vous présentons une maquette en couleurs et des échantillons de matériaux.',
            'La mosaïque se prête particulièrement aux projets collectifs : chacun pose ses tesselles sur une partie de l’œuvre, guidé par l’atelier, et le résultat devient le symbole durable d’un quartier ou d’une école. Pour les vitraux, nous proposons aussi des créations en verre peint qui évoquent le vitrail traditionnel.',
        ],
        'features' => [
            ['title' => 'Mosaïque murale', 'text' => 'Des fresques en tesselles de céramique, de verre ou de pierre.'],
            ['title' => 'Sols, tables & fontaines', 'text' => 'Des surfaces décorées pensées pour l’usage et l’entretien.'],
            ['title' => 'Vitrail', 'text' => 'Verre coloré et réseau de plomb pour des fenêtres qui rayonnent.'],
            ['title' => 'Verre peint', 'text' => 'Un décor lumineux inspiré du vitrail, sur portes ou vitres existantes.'],
            ['title' => 'Projets collectifs', 'text' => 'Une œuvre posée avec les habitants, les élèves ou les fidèles.'],
            ['title' => 'Maquette & échantillons', 'text' => 'Couleurs et matériaux validés avant la réalisation.'],
        ],
        'process' => [
            ['title' => 'Le lieu & la lumière', 'text' => 'Support, orientation, usages et contraintes techniques.'],
            ['title' => 'Le carton', 'text' => 'Dessin à l’échelle et choix des couleurs et matériaux.'],
            ['title' => 'L’assemblage', 'text' => 'Découpe, pose des tesselles ou montage du vitrail.'],
            ['title' => 'Les joints & la pose', 'text' => 'Jointoiement, nettoyage, protection et installation.'],
        ],
        'ideal_for' => [
            'Lieux de culte',
            'Écoles et bâtiments publics',
            'Maisons, entrées et salles de bain',
            'Jardins, fontaines et espaces extérieurs',
        ],
        'faq' => [
            [
                'q' => 'Une mosaïque extérieure résiste-t-elle au temps ?',
                'a' => 'Oui, avec des matériaux et des colles adaptés à l’extérieur et un support sain. Nous vérifions le support avant tout projet.',
            ],
            [
                'q' => 'Pouvez-vous réparer un vitrail existant ?',
                'a' => 'Pour un vitrail ancien ou classé, nous vous orientons vers un restaurateur spécialisé. Pour des créations récentes, nous étudions chaque cas avec vous.',
            ],
            [
                'q' => 'Les enfants peuvent-ils participer à une mosaïque ?',
                'a' => 'Oui. Nous préparons des zones et des outils adaptés à leur âge ; c’est un projet idéal pour une école ou un centre de loisirs.',
            ],
            [
                'q' => 'Quelle différence entre vitrail et verre peint ?',
                'a' => 'Le vitrail assemble des morceaux de verre coloré avec du plomb. Le verre peint décore une vitre existante : plus léger et plus simple à mettre en œuvre.',
            ],
        ],
        'scene_alt' => 'Les vitres d’une fenêtre en ogive s’allument une à une sous le soleil et projettent des rayons colorés sur le sol.',
        'meta_description' => 'Mosaïques murales, sols et fontaines, vitraux et verre peint pour maisons, écoles et lieux de culte, y compris en projets collectifs. Devis gratuit.',
    ],
    'en' => [
        'title' => 'Mosaic & stained glass',
        'short' => 'Tesserae and coloured glass that make light sing on your walls and windows.',
        'tagline' => 'A thousand fragments, one single light.',
        'intro' => 'Mosaic and stained glass share one secret: assembling fragments to create an image larger than themselves. The atelier makes wall mosaics, decorated floors and tables, tesserae murals with residents, as well as stained glass and painted-glass decorations for homes, schools and places of worship.',
        'body' => [
            'The design is conceived for the technique from the start: cutting the tesserae, grout and resistance to frost or damp for mosaic; lead lines, transparency and the direction of light for stained glass. We present a colour mock-up and material samples.',
            'Mosaic lends itself particularly well to collective projects: everyone sets their tesserae on part of the work, guided by the atelier, and the result becomes a lasting symbol of a neighbourhood or a school. We also offer painted-glass creations that evoke traditional stained glass.',
        ],
        'features' => [
            ['title' => 'Wall mosaics', 'text' => 'Murals in ceramic, glass or stone tesserae.'],
            ['title' => 'Floors, tables & fountains', 'text' => 'Decorated surfaces designed for use and upkeep.'],
            ['title' => 'Stained glass', 'text' => 'Coloured glass and lead lines for radiant windows.'],
            ['title' => 'Painted glass', 'text' => 'A luminous, stained-glass-inspired decoration on existing doors or panes.'],
            ['title' => 'Collective projects', 'text' => 'A work set with residents, pupils or congregations.'],
            ['title' => 'Mock-up & samples', 'text' => 'Colours and materials approved before making.'],
        ],
        'process' => [
            ['title' => 'Place & light', 'text' => 'Surface, orientation, uses and technical constraints.'],
            ['title' => 'The cartoon', 'text' => 'A full-scale drawing and the choice of colours and materials.'],
            ['title' => 'The assembly', 'text' => 'Cutting and setting the tesserae, or building the window.'],
            ['title' => 'Grout & installation', 'text' => 'Grouting, cleaning, protection and installation.'],
        ],
        'ideal_for' => [
            'Places of worship',
            'Schools and public buildings',
            'Homes, entrances and bathrooms',
            'Gardens, fountains and outdoor spaces',
        ],
        'faq' => [
            [
                'q' => 'Does an outdoor mosaic stand the test of time?',
                'a' => 'Yes, with outdoor-grade materials and adhesives on a sound surface. We check the surface before any project.',
            ],
            [
                'q' => 'Can you repair an existing stained-glass window?',
                'a' => 'For an old or listed window, we refer you to a specialist conservator. For recent creations, we look at each case with you.',
            ],
            ['q' => 'Can children take part in a mosaic?', 'a' => 'Yes. We prepare areas and tools suited to their age; it is an ideal project for a school or a youth centre.'],
            [
                'q' => 'What is the difference between stained and painted glass?',
                'a' => 'Stained glass assembles pieces of coloured glass with lead. Painted glass decorates an existing pane: lighter and simpler to install.',
            ],
        ],
        'scene_alt' => 'The panes of a pointed-arch window light up one by one in the sun and cast coloured rays on the floor.',
        'meta_description' => 'Wall mosaics, floors and fountains, stained and painted glass for homes, schools and places of worship, including collective projects. Free quote.',
    ],
];
