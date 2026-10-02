<?php

// Service n°20 — Illustration & bande dessinée (docs/ARCHITECTURE.md §4).
// French is the main language; English is its translation. Edit the copy here.

return [
    'slug' => 'illustration-bande-dessinee',
    'order' => 20,
    'category' => 'image',
    'accent' => 'blue',
    'icon' => 'book',
    'art_style' => 'prisme',
    'scene' => 'illustration-bande-dessinee',
    'fr' => [
        'title' => 'Illustration & bande dessinée',
        'short' => 'Albums jeunesse, BD, affiches illustrées et supports pédagogiques dessinés avec soin.',
        'tagline' => 'Raconter en images ce que les mots ne disent pas seuls.',
        'intro' => 'Une illustration peut expliquer, émouvoir et faire rêver en un seul regard. L’atelier dessine des albums jeunesse, des bandes dessinées, des affiches illustrées, des cartes, des supports pédagogiques et des histoires de quartier racontées en planches. Crayon, encre, aquarelle ou couleur numérique, au service de votre récit.',
        'body' => [
            'Nous commençons par le texte ou l’idée : public visé, ton, nombre d’images et format de publication. Viennent ensuite les recherches de personnages, le découpage en pages ou en cases, puis les crayonnés que nous ajustons avec vous avant l’encrage et la couleur.',
            'L’illustration est un merveilleux outil communautaire : transformer les souvenirs d’un quartier en bande dessinée, illustrer un livret de sensibilisation, créer les personnages d’un projet d’école. Nous livrons des fichiers prêts pour l’impression et pouvons accompagner vos échanges avec l’imprimeur.',
        ],
        'features' => [
            ['title' => 'Albums jeunesse', 'text' => 'Des personnages attachants et des pages pensées pour les enfants.'],
            ['title' => 'Bande dessinée', 'text' => 'Découpage, cases, bulles et mise en couleurs d’un récit.'],
            ['title' => 'Affiches illustrées', 'text' => 'Des affiches qui racontent une histoire au premier regard.'],
            ['title' => 'Supports pédagogiques', 'text' => 'Des schémas et illustrations claires pour apprendre et sensibiliser.'],
            ['title' => 'Histoires de quartier', 'text' => 'Les mémoires d’une communauté mises en images.'],
            ['title' => 'Prêt pour l’impression', 'text' => 'Fichiers haute définition préparés pour l’imprimeur.'],
        ],
        'process' => [
            ['title' => 'Le récit', 'text' => 'Le texte, le public, le ton et le format de publication.'],
            ['title' => 'Les personnages', 'text' => 'Recherches graphiques et découpage en pages.'],
            ['title' => 'Les crayonnés', 'text' => 'Mises en page dessinées, ajustées avec vous.'],
            ['title' => 'Encrage & couleur', 'text' => 'Finalisation des images et préparation des fichiers.'],
        ],
        'ideal_for' => [
            'Auteurs et éditeurs indépendants',
            'Écoles et bibliothèques',
            'Associations et campagnes de sensibilisation',
            'Mairies et projets de mémoire',
        ],
        'faq' => [
            [
                'q' => 'Pouvez-vous illustrer un livre que j’ai écrit ?',
                'a' => 'Oui. Nous lisons votre texte, proposons des personnages et un découpage, puis illustrons page après page en validant chaque étape avec vous.',
            ],
            [
                'q' => 'Dans quel style dessinez-vous ?',
                'a' => 'Plusieurs écritures sont possibles, du trait jeunesse tendre au style graphique et géométrique de l’atelier. Nous vous montrons des pistes avant de commencer.',
            ],
            [
                'q' => 'Qui détient les droits des illustrations ?',
                'a' => 'Les conditions d’utilisation et de cession sont précisées dans le devis, selon votre projet de publication et de diffusion.',
            ],
            [
                'q' => 'Combien de temps faut-il pour un album ?',
                'a' => 'Cela dépend du nombre de pages et de la technique. Nous établissons un calendrier avec des étapes de validation claires.',
            ],
        ],
        'scene_alt' => 'Des cases de bande dessinée apparaissent une à une, un crayon les encre et une bulle s’ouvre avec un triangle.',
        'meta_description' => 'Illustration et bande dessinée : albums jeunesse, BD, affiches illustrées, supports pédagogiques et histoires de quartier en images. Devis gratuit.',
    ],
    'en' => [
        'title' => 'Illustration & comics',
        'short' => 'Children’s books, comics, illustrated posters and educational materials, drawn with care.',
        'tagline' => 'Telling in pictures what words cannot say alone.',
        'intro' => 'An illustration can explain, move and make people dream at a single glance. The atelier draws children’s books, comics, illustrated posters, maps, educational materials and neighbourhood stories told in comic strips. Pencil, ink, watercolour or digital colour, in the service of your story.',
        'body' => [
            'We start with the text or the idea: target audience, tone, number of images and publication format. Then come character studies, the page or panel breakdown, and the pencil roughs we adjust with you before inking and colouring.',
            'Illustration is a wonderful community tool: turning a neighbourhood’s memories into a comic, illustrating an awareness booklet, creating the characters of a school project. We deliver print-ready files and can help with your exchanges with the printer.',
        ],
        'features' => [
            ['title' => 'Children’s books', 'text' => 'Endearing characters and pages designed for children.'],
            ['title' => 'Comics', 'text' => 'Breakdown, panels, speech bubbles and colouring of a story.'],
            ['title' => 'Illustrated posters', 'text' => 'Posters that tell a story at first glance.'],
            ['title' => 'Educational materials', 'text' => 'Clear diagrams and illustrations for learning and awareness.'],
            ['title' => 'Neighbourhood stories', 'text' => 'A community’s memories put into pictures.'],
            ['title' => 'Print-ready', 'text' => 'High-resolution files prepared for the printer.'],
        ],
        'process' => [
            ['title' => 'The story', 'text' => 'The text, the audience, the tone and the publication format.'],
            ['title' => 'The characters', 'text' => 'Graphic research and page breakdown.'],
            ['title' => 'The roughs', 'text' => 'Drawn layouts, adjusted with you.'],
            ['title' => 'Ink & colour', 'text' => 'Final images and file preparation.'],
        ],
        'ideal_for' => [
            'Authors and independent publishers',
            'Schools and libraries',
            'Associations and awareness campaigns',
            'Town halls and memory projects',
        ],
        'faq' => [
            [
                'q' => 'Can you illustrate a book I wrote?',
                'a' => 'Yes. We read your text, suggest characters and a breakdown, then illustrate page by page, approving each step with you.',
            ],
            [
                'q' => 'What style do you draw in?',
                'a' => 'Several styles are possible, from a gentle children’s line to the atelier’s graphic, geometric style. We show you directions before starting.',
            ],
            [
                'q' => 'Who owns the rights to the illustrations?',
                'a' => 'Terms of use and transfer of rights are set out in the quote, according to your publishing and distribution plans.',
            ],
            ['q' => 'How long does a picture book take?', 'a' => 'It depends on the number of pages and the technique. We draw up a schedule with clear approval steps.'],
        ],
        'scene_alt' => 'Comic panels appear one by one, a pencil inks them and a speech bubble opens with a triangle.',
        'meta_description' => 'Illustration and comics: children’s books, comic strips, illustrated posters, educational materials and neighbourhood stories in pictures. Free quote.',
    ],
];
