<?php

// Service n°17 — Maquillage artistique (docs/ARCHITECTURE.md §4).
// French is the main language; English is its translation. Edit the copy here.

return [
    'slug' => 'maquillage-artistique',
    'order' => 17,
    'category' => 'espaces',
    'accent' => 'yellow',
    'icon' => 'mask',
    'art_style' => 'soleil',
    'scene' => 'maquillage-artistique',
    'fr' => [
        'title' => 'Maquillage artistique',
        'short' => 'Maquillage de fête, body painting et grimage pour enfants, festivals, carnavals et spectacles.',
        'tagline' => 'Le visage devient une toile, le temps d’une fête.',
        'intro' => 'Le maquillage artistique transforme un visage en œuvre éphémère. L’atelier intervient lors des fêtes d’école, des anniversaires, des carnavals, des festivals, des kermesses et des spectacles : grimage pour les enfants, maquillages graphiques inspirés de nos triangles, body painting pour la scène ou les séances photo.',
        'body' => [
            'Nous utilisons des fards à l’eau conçus pour la peau, faciles à démaquiller, ainsi que des pinceaux et éponges propres pour chaque personne. Avant de maquiller, nous demandons toujours l’accord de l’enfant et de son parent, et nous évitons les zones sensibles ou irritées.',
            'Pour un événement, nous proposons un catalogue de motifs adaptés au thème, afin que chacun choisisse rapidement. Pour un spectacle ou une séance photo, nous concevons des maquillages sur mesure en lien avec les costumes et l’éclairage, du simple accent coloré au corps entièrement peint.',
        ],
        'features' => [
            ['title' => 'Grimage enfants', 'text' => 'Animaux, super-héros, fleurs et motifs géométriques, en quelques minutes.'],
            ['title' => 'Body painting', 'text' => 'Des créations sur le corps pour la scène, la photo ou un défilé.'],
            ['title' => 'Produits adaptés', 'text' => 'Des fards à l’eau conçus pour la peau et faciles à retirer.'],
            ['title' => 'Hygiène soignée', 'text' => 'Matériel propre pour chaque personne et accord préalable systématique.'],
            ['title' => 'Catalogue à thème', 'text' => 'Des motifs choisis selon votre fête pour un passage fluide.'],
            ['title' => 'Spectacles & photos', 'text' => 'Des maquillages pensés avec les costumes et la lumière.'],
        ],
        'process' => [
            ['title' => 'L’événement', 'text' => 'Date, durée, nombre de participants et thème.'],
            ['title' => 'Les motifs', 'text' => 'Un catalogue ou des maquillages sur mesure à valider.'],
            ['title' => 'Le jour J', 'text' => 'Installation d’un stand coloré et maquillages en continu.'],
            ['title' => 'Les souvenirs', 'text' => 'Conseils de démaquillage et photos si vous le souhaitez.'],
        ],
        'ideal_for' => [
            'Fêtes d’école et anniversaires',
            'Carnavals et défilés',
            'Festivals et kermesses',
            'Spectacles et séances photo',
        ],
        'faq' => [
            [
                'q' => 'Les produits conviennent-ils aux peaux sensibles ?',
                'a' => 'Nous utilisons des fards à l’eau conçus pour la peau. En cas d’allergie connue ou de peau irritée, nous ne maquillons pas la zone concernée et proposons une alternative.',
            ],
            [
                'q' => 'Combien de personnes pouvez-vous maquiller ?',
                'a' => 'Cela dépend de la complexité des motifs et de la durée de l’événement. Nous estimons ensemble le rythme et le nombre d’intervenants nécessaires.',
            ],
            ['q' => 'Le maquillage s’enlève-t-il facilement ?', 'a' => 'Oui, avec de l’eau et un savon doux. Nous remettons des conseils de démaquillage aux familles.'],
            [
                'q' => 'Proposez-vous des maquillages pour adultes ?',
                'a' => 'Oui : maquillages graphiques pour les fêtes, body painting pour la scène ou la photo, toujours dans le respect et avec votre accord.',
            ],
        ],
        'scene_alt' => 'Sur un visage de profil, un pinceau trace des triangles colorés qui s’illuminent comme un soleil.',
        'meta_description' => 'Maquillage artistique et body painting : grimage pour enfants, fêtes d’école, carnavals, festivals et spectacles, avec des produits adaptés à la peau.',
    ],
    'en' => [
        'title' => 'Face & body painting',
        'short' => 'Party face painting, body painting and make-up for children, festivals, carnivals and shows.',
        'tagline' => 'For one celebration, the face becomes a canvas.',
        'intro' => 'Artistic make-up turns a face into an ephemeral artwork. The atelier works at school fairs, birthdays, carnivals, festivals, fêtes and shows: face painting for children, graphic make-up inspired by our triangles, and body painting for the stage or photo shoots.',
        'body' => [
            'We use water-based face paints designed for skin and easy to remove, with clean brushes and sponges for each person. Before painting, we always ask for the consent of the child and their parent, and we avoid sensitive or irritated areas.',
            'For an event, we offer a catalogue of designs matching the theme so everyone can choose quickly. For a show or a photo shoot, we create custom make-up designed with the costumes and lighting, from a simple touch of colour to a fully painted body.',
        ],
        'features' => [
            ['title' => 'Children’s face painting', 'text' => 'Animals, superheroes, flowers and geometric designs in minutes.'],
            ['title' => 'Body painting', 'text' => 'Creations on the body for the stage, photography or a parade.'],
            ['title' => 'Skin-friendly products', 'text' => 'Water-based paints designed for skin and easy to remove.'],
            ['title' => 'Careful hygiene', 'text' => 'Clean materials for each person and consent asked every time.'],
            ['title' => 'Themed catalogue', 'text' => 'Designs chosen for your event for a smooth flow.'],
            ['title' => 'Shows & photos', 'text' => 'Make-up designed with the costumes and the light.'],
        ],
        'process' => [
            ['title' => 'The event', 'text' => 'Date, duration, number of participants and theme.'],
            ['title' => 'The designs', 'text' => 'A catalogue or custom make-up to approve.'],
            ['title' => 'On the day', 'text' => 'A colourful stand and continuous face painting.'],
            ['title' => 'The memories', 'text' => 'Removal tips and photos if you wish.'],
        ],
        'ideal_for' => ['School fairs and birthdays', 'Carnivals and parades', 'Festivals and fêtes', 'Shows and photo shoots'],
        'faq' => [
            [
                'q' => 'Are the products suitable for sensitive skin?',
                'a' => 'We use water-based paints designed for skin. If there is a known allergy or irritated skin, we do not paint that area and suggest an alternative.',
            ],
            [
                'q' => 'How many people can you paint?',
                'a' => 'It depends on the complexity of the designs and the length of the event. Together we estimate the pace and the number of artists needed.',
            ],
            ['q' => 'Does the make-up come off easily?', 'a' => 'Yes, with water and a mild soap. We give families removal tips.'],
            [
                'q' => 'Do you offer make-up for adults?',
                'a' => 'Yes: graphic make-up for parties and body painting for the stage or photography, always respectfully and with your consent.',
            ],
        ],
        'scene_alt' => 'On a face in profile, a brush draws coloured triangles that light up like a sun.',
        'meta_description' => 'Face and body painting: children’s face painting, school fairs, carnivals, festivals and shows, using skin-friendly products. Ateliers Pehouet.',
    ],
];
