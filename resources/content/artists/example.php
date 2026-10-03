<?php

// The example artist of the artist pages (docs/ARTISTS.md §4.4, §8): a fictional painter and generative artist,
// imported UNPUBLISHED and flagged "Exemple" by App\Artists\ExampleArtist::create() (admin button), then edited
// or deleted by the owner in the admin. French is the main language; English is its translation.
// Entirely fictional: no real institutions, galleries, people or prizes (venues are invented or the atelier).
//
// Images: example/{file}.webp (longest edge 1600) + {file}-480.webp and {file}-960.webp (480 / 960 px wide).
// The artworks were painted by the atelier's own engine (python/art_engine, one style per work), the portrait
// is a hand-built faceted bust and the exhibition visuals are rendered installation views.
// Optional 'alt' => ['fr' => …, 'en' => …] on an entry = the photo's alternative text (else its title).
// Exhibition dates are modifiers of today ('-20 days'), so the example always has a current, an upcoming and
// past shows; 'years_ago' places the year-only entries.

return [
    'artist' => [
        'slug' => 'camille-durand',
        'name' => 'Camille Durand',
        'accent' => 'yellow',
        'location' => 'Lyon, France',
        'website' => null,
        'instagram' => null,
        'portrait' => [
            'file' => 'portrait',
            'alt' => [
                'fr' => "Portrait de Camille Durand\u{202F}: un buste vu de trois quarts, composé de facettes triangulaires bleues, jaunes et rouges cousues de blanc, sur fond noir.",
                'en' => 'Portrait of Camille Durand: a three-quarter bust built from blue, yellow and red triangular facets stitched with white, on black.',
            ],
        ],
        'fr' => [
            'discipline' => 'Peintre & artiste générative',
            'statement' => "Je peins des triangles comme on écrit des phrases\u{202F}: un champ de couleur, une couture blanche, puis le suivant. Entre le calcul et le geste, je cherche l’endroit exact où une forme simple devient un lieu que l’on partage.",
            'bio' => implode("\n\n", [
                "**Camille Durand** peint des champs de couleur que traversent de fines coutures blanches. Née en 1988, formée au dessin puis au code, elle vit et travaille à Lyon, où une forme unique — le **triangle** — est devenue le point de départ de toute son œuvre\u{202F}: elle le découpe, le recompose et le répète jusqu’à en faire un territoire, un vitrail, un tissage.",
                "Chaque tableau commence par un programme. Camille écrit de courts algorithmes qui proposent des partitions du plan\u{202F}: triangles qui se divisent, bandes qui se croisent, rayons qui s’ouvrent autour d’un foyer. Elle en génère des centaines, en retient une poignée, puis reprend tout à la main — à l’acrylique sur toile, en sérigraphie ou en impression pigmentaire. Trois règles tiennent l’atelier\u{202F}:",
                "- une palette restreinte\u{202F}: bleu, jaune, rouge, blanc, noir, et les ambres du soir\u{202F};\n- un joint blanc entre chaque champ, comme une couture\u{202F};\n- *le hasard propose, la main décide.*",
                "Aux **Ateliers Pehouet**, elle anime des ateliers ouverts où enfants, voisins et artistes amateurs composent ensemble de grandes fresques triangulaires. Chacun peint son fragment, le programme suggère un assemblage, le groupe tranche. Pour elle, *l’art au service de la communauté* n’est pas un slogan mais une méthode\u{202F}: l’œuvre achevée garde la trace de toutes les mains qui l’ont faite.",
                "Son travail a été présenté dans des expositions personnelles et collectives, lors d’une résidence de recherche et sur des salons consacrés à l’estampe\u{202F}; il figure dans plusieurs collections particulières. Elle développe aujourd’hui la série *Trames*, des compositions tissées par programme et achevées à la main, proposées sur commande au format de chaque lieu.",
            ]),
            'meta' => "Camille Durand, peintre et artiste générative à Lyon\u{202F}: triangles, champs de couleur et coutures blanches. Biographie, œuvres et expositions.",
        ],
        'en' => [
            'discipline' => 'Painter & generative artist',
            'statement' => 'I paint triangles the way one writes sentences: a field of colour, a white seam, then the next. Between calculation and gesture, I look for the exact point where a simple shape becomes a place we share.',
            'bio' => implode("\n\n", [
                '**Camille Durand** paints fields of colour crossed by fine white seams. Born in 1988 and trained first in drawing, then in code, she lives and works in Lyon, where a single shape — the **triangle** — has become the starting point of her entire body of work: she cuts it up, recomposes it and repeats it until it becomes a territory, a stained-glass window, a weave.',
                'Every painting begins with a program. Camille writes short algorithms that propose ways of dividing the picture plane: triangles that split, bands that cross, rays that open around a burning core. She generates hundreds of them, keeps a handful, then takes everything back by hand — in acrylic on canvas, as screenprints or as pigment prints. Three rules run the studio:',
                "- a restricted palette: blue, yellow, red, white, black, and the ambers of evening;\n- a white seam between every field, like a stitch;\n- *chance proposes, the hand decides.*",
                'At the **Ateliers Pehouet**, she leads open workshops where children, neighbours and amateur artists compose large triangular murals together. Everyone paints a fragment, the program suggests an arrangement, the group decides. For her, *art in the service of the community* is not a slogan but a method: the finished piece keeps the trace of every hand that made it.',
                'Her work has been shown in solo and group exhibitions, during a research residency and at print fairs, and is held in several private collections. She is currently developing *Weft*, a series of compositions woven by a program and finished by hand, offered as commissions sized to each space.',
            ]),
            'meta' => 'Camille Durand, painter and generative artist based in Lyon: triangles, fields of colour and white seams. Biography, works and exhibitions.',
        ],
    ],

    // In display order (the works grid: portrait, landscape, square, panoramic, portrait, landscape, portrait, landscape).
    'artworks' => [
        [
            'file' => 'oeuvre-01', // art engine: pehouet, 1280 × 1600
            'year' => '2025',
            'dimensions' => '100 × 80 cm',
            'availability' => 'available',
            'alt' => [
                'fr' => 'Grand triangle lumineux sur fond noir, partagé en champs bleus, jaune et rouge par des joints blancs, doublé d’un contour orangé et traversé de fines diagonales blanches.',
                'en' => 'A large glowing triangle on black, divided into blue, yellow and red fields by white seams, echoed by an orange outline and crossed by thin white diagonals.',
            ],
            'fr' => [
                'title' => 'Grand Triangle, nocturne',
                'medium' => 'Acrylique sur toile, d’après une composition générative',
                'description' => "Pièce centrale de la série «\u{202F}Coutures\u{202F}». Le programme a proposé la partition du triangle\u{202F}; chaque champ a ensuite été peint en trois couches, les joints blancs tracés à la main et le halo monté en glacis successifs.",
            ],
            'en' => [
                'title' => 'Great Triangle, Nocturne',
                'medium' => 'Acrylic on canvas, after a generative composition',
                'description' => 'The centrepiece of the “Seams” series. The program proposed how to divide the triangle; each field was then painted in three coats, the white seams drawn by hand and the halo built up in successive glazes.',
            ],
        ],
        [
            'file' => 'oeuvre-02', // art engine: mondrian, 1600 × 1067
            'year' => '2024',
            'dimensions' => '50 × 75 cm',
            'availability' => 'available',
            'alt' => [
                'fr' => 'Rectangles bleus, rouges, jaunes et noirs séparés par de larges joints blancs, avec un triangle noir et un carré coupé en diagonale, rouge et jaune.',
                'en' => 'Blue, red, yellow and black rectangles separated by wide white seams, with a black triangle and a square split diagonally into red and yellow.',
            ],
            'fr' => [
                'title' => 'Îlots, matin',
                'medium' => "Sérigraphie sept couleurs sur papier 300\u{00A0}g, édition de\u{00A0}20",
                'description' => "Le plan d’une ville imaginaire vu d’en haut\u{202F}: des rues blanches, des îlots de couleur pure. Chaque couleur a son propre écran, tiré à la main à l’atelier.",
            ],
            'en' => [
                'title' => 'City Blocks, Morning',
                'medium' => "Seven-colour screenprint on 300\u{00A0}gsm paper, edition of\u{00A0}20",
                'description' => 'The map of an imaginary city seen from above: white streets, blocks of pure colour. Each colour has its own screen, pulled by hand in the studio.',
            ],
        ],
        [
            'file' => 'oeuvre-03', // art engine: soleil, 1600 × 1600
            'year' => '2023',
            'dimensions' => '90 × 90 cm',
            'availability' => 'sold',
            'alt' => [
                'fr' => 'Rayons bleus, jaunes et rouges convergeant vers un petit triangle incandescent, cernés de fins contours triangulaires.',
                'en' => 'Blue, yellow and red rays converging on a small glowing triangle, ringed by thin triangular outlines.',
            ],
            'fr' => [
                'title' => 'Le Foyer',
                'medium' => 'Acrylique et encre sur toile, d’après une composition générative',
                'description' => 'Des rayons bleus, jaunes et rouges convergent vers un petit triangle incandescent. Peint pour l’ouverture des ateliers du samedi, comme une invitation à se rassembler.',
            ],
            'en' => [
                'title' => 'The Hearth',
                'medium' => 'Acrylic and ink on canvas, after a generative composition',
                'description' => 'Blue, yellow and red rays converge on a small glowing triangle. Painted for the first Saturday workshops, as an invitation to gather.',
            ],
        ],
        [
            'file' => 'oeuvre-04', // art engine: tissage, 1600 × 640
            'year' => '2026',
            'dimensions' => '60 × 150 cm',
            'availability' => 'commission',
            'alt' => [
                'fr' => 'Bandes bleues, jaunes, rouges, ambre et blanches tissées en diagonale, avec au centre un triangle rouge cerné de noir et de blanc.',
                'en' => 'Blue, yellow, red, amber and white bands woven on the diagonal, with a red triangle outlined in black and white at the centre.',
            ],
            'fr' => [
                'title' => 'Trame et chaîne',
                'medium' => "Impression pigmentaire sur papier coton 310\u{00A0}g",
                'description' => "Première pièce de la série «\u{202F}Trames\u{202F}», en hommage aux tisserands de la soie. Proposée sur commande\u{202F}: le programme tisse une composition unique aux dimensions de votre mur, que l’artiste ajuste avec vous.",
            ],
            'en' => [
                'title' => 'Warp and Weft',
                'medium' => "Pigment print on 310\u{00A0}gsm cotton paper",
                'description' => 'The first piece of the “Weft” series, a tribute to the silk weavers. Available as a commission: the program weaves a unique composition sized to your wall, which the artist fine-tunes with you.',
            ],
        ],
        [
            'file' => 'oeuvre-05', // art engine: vitrail, 1067 × 1600
            'year' => '2022',
            'dimensions' => '120 × 80 cm',
            'availability' => 'collection',
            'alt' => [
                'fr' => 'Fenêtre en plein cintre faite d’éclats bleus, jaunes et rouges sertis de plomb noir, dont la lumière dorée se projette au sol.',
                'en' => 'A round-arched window of blue, yellow and red shards set in black lead, casting golden light onto the floor.',
            ],
            'fr' => [
                'title' => 'Vitrail pour une cage d’escalier',
                'medium' => 'Impression pigmentaire sur film translucide, caisson lumineux',
                'description' => "Créé avec les habitants d’un immeuble lors d’un atelier participatif\u{202F}: chacun a choisi la couleur d’un éclat. Le caisson éclaire aujourd’hui leur cage d’escalier.",
            ],
            'en' => [
                'title' => 'Stained Glass for a Stairwell',
                'medium' => 'Pigment print on translucent film, lightbox',
                'description' => 'Made with the residents of an apartment building during a participatory workshop: each of them chose the colour of one shard. The lightbox now lights their stairwell.',
            ],
        ],
        [
            'file' => 'oeuvre-06', // art engine: mosaique, 1600 × 1200
            'year' => '2025',
            'dimensions' => '90 × 120 cm',
            'availability' => 'reserved',
            'alt' => [
                'fr' => 'Mosaïque de petits triangles sombres d’où émerge un grand triangle jaune, bleu et rouge ponctué de blanc.',
                'en' => 'A mosaic of small dark triangles from which a large yellow, blue and red triangle emerges, dotted with white.',
            ],
            'fr' => [
                'title' => 'Assemblée',
                'medium' => 'Acrylique sur bois, d’après une composition générative',
                'description' => "Des centaines de tesselles, une seule grande figure\u{202F}: l’image même des ateliers ouverts, où chaque fragment compte pour le tout.",
            ],
            'en' => [
                'title' => 'Assembly',
                'medium' => 'Acrylic on wood panel, after a generative composition',
                'description' => 'Hundreds of tesserae, one large figure: the very image of the open workshops, where every fragment counts towards the whole.',
            ],
        ],
        [
            'file' => 'oeuvre-07', // art engine: prisme, 1200 × 1600
            'year' => '2021',
            'dimensions' => '120 × 90 cm',
            'availability' => 'available',
            'alt' => [
                'fr' => 'Facettes triangulaires ambre, orange, bleues et rouges, avec un grand contour de triangle blanc au centre.',
                'en' => 'Amber, orange, blue and red triangular facets, with a large white triangle outline at the centre.',
            ],
            'fr' => [
                'title' => 'Prisme, fin d’après-midi',
                'medium' => 'Acrylique sur toile, d’après une composition générative',
                'description' => 'Les triangles se divisent comme la lumière dans un prisme, des ambres chauds du haut vers les bleus et les rouges du bas. Un contour blanc marque le centre de gravité.',
            ],
            'en' => [
                'title' => 'Prism, Late Afternoon',
                'medium' => 'Acrylic on canvas, after a generative composition',
                'description' => 'Triangles divide like light through a prism, from warm ambers at the top to the blues and reds below. A white outline marks the centre of gravity.',
            ],
        ],
        [
            'file' => 'oeuvre-08', // art engine: eclats, 1600 × 900
            'year' => '2020',
            'dimensions' => '45 × 80 cm',
            'availability' => 'none',
            'alt' => [
                'fr' => 'Éclats bleus, jaunes et rouges et longues diagonales blanches jaillissant d’un triangle incandescent sur fond noir.',
                'en' => 'Blue, yellow and red shards and long white diagonals bursting from a glowing triangle on black.',
            ],
            'fr' => [
                'title' => 'Feu d’artifice pour une seule personne',
                'medium' => 'Impression pigmentaire contrecollée sur aluminium',
                'description' => 'La toute première image produite par le programme que Camille utilise encore aujourd’hui. L’artiste en a gardé l’unique épreuve.',
            ],
            'en' => [
                'title' => 'Fireworks for an Audience of One',
                'medium' => 'Pigment print mounted on aluminium',
                'description' => 'The very first image produced by the program Camille still uses today. The artist kept its only proof.',
            ],
        ],
    ],

    'exhibitions' => [
        [
            'kind' => 'solo',
            'venue' => 'Ateliers Pehouet',
            'city' => null, // the atelier's address is not published on the site
            'starts' => '-20 days',
            'ends' => '+40 days',
            'years_ago' => null,
            'url' => null,
            'file' => 'expo-01',
            'alt' => [
                'fr' => "Vue de l’exposition «\u{202F}Coutures de lumière\u{202F}»\u{202F}: trois œuvres de Camille Durand éclairées par des projecteurs sur un mur sombre.",
                'en' => 'Installation view of “Seams of Light”: three works by Camille Durand lit by spotlights on a dark wall.',
            ],
            'fr' => [
                'title' => 'Coutures de lumière',
                'description' => "Première exposition personnelle de Camille Durand aux Ateliers Pehouet\u{202F}: peintures, impressions pigmentaires et une fresque participative qui s’agrandit à chaque atelier ouvert du samedi.",
            ],
            'en' => [
                'title' => 'Seams of Light',
                'description' => 'Camille Durand’s first solo exhibition at the Ateliers Pehouet: paintings, pigment prints and a participatory mural that grows with every open Saturday workshop.',
            ],
        ],
        [
            'kind' => 'group',
            'venue' => 'Fondation Ilse-Varenne',
            'city' => 'Marseille',
            'starts' => '+75 days',
            'ends' => '+120 days',
            'years_ago' => null,
            'url' => null,
            'file' => null,
            'fr' => [
                'title' => 'Géométries partagées',
                'description' => "Six artistes autour d’une même grammaire de formes simples. Camille Durand y présente la série «\u{202F}Trames\u{202F}» au complet, tissée par programme et achevée à la main.",
            ],
            'en' => [
                'title' => 'Shared Geometries',
                'description' => 'Six artists sharing one grammar of simple shapes. Camille Durand shows the complete “Weft” series, woven by a program and finished by hand.',
            ],
        ],
        [
            'kind' => 'fair',
            'venue' => 'Halle Vermeille',
            'city' => 'Lille',
            'starts' => '-430 days',
            'ends' => '-426 days',
            'years_ago' => null,
            'url' => null,
            'file' => null,
            'fr' => [
                'title' => 'Salon Papier Ligne',
                'description' => 'Sur le stand des Ateliers Pehouet, une sélection d’estampes et d’impressions pigmentaires en petites éditions.',
            ],
            'en' => [
                'title' => 'Papier Ligne Art Fair',
                'description' => 'On the Ateliers Pehouet stand, a selection of screenprints and pigment prints in small editions.',
            ],
        ],
        [
            'kind' => 'group',
            'venue' => 'Galerie Aubertin-Lacaze',
            'city' => 'Nantes',
            'starts' => '-700 days',
            'ends' => '-640 days',
            'years_ago' => null,
            'url' => null,
            'file' => 'expo-02',
            'alt' => [
                'fr' => "Vue de l’exposition «\u{202F}Lignes de partage\u{202F}»\u{202F}: quatre œuvres de Camille Durand, dont un caisson lumineux, dans une salle sombre.",
                'en' => 'Installation view of “Dividing Lines”: four works by Camille Durand, including a lightbox, in a dark gallery room.',
            ],
            'fr' => [
                'title' => 'Lignes de partage',
                'description' => 'Exposition collective autour de la frontière et du joint. Camille Durand y montrait quatre pièces, de la toile au caisson lumineux.',
            ],
            'en' => [
                'title' => 'Dividing Lines',
                'description' => 'A group exhibition about borders and seams. Camille Durand showed four pieces, from canvas to lightbox.',
            ],
        ],
        [
            'kind' => 'residency',
            'venue' => 'Maison Céleste-Arnaud',
            'city' => 'Grenoble',
            'starts' => null,
            'ends' => null,
            'years_ago' => 3,
            'url' => null,
            'file' => null,
            'fr' => [
                'title' => 'La main et le code',
                'description' => "Trois mois de recherche sur le passage de l’écran à la toile\u{202F}: protocoles de report, gammes de couleurs et premiers grands formats.",
            ],
            'en' => [
                'title' => 'The Hand and the Code',
                'description' => 'Three months of research into moving from screen to canvas: transfer protocols, colour ranges and the first large formats.',
            ],
        ],
        [
            'kind' => 'group',
            'venue' => 'Atelier-galerie Rive Haute',
            'city' => 'Lyon',
            'starts' => null,
            'ends' => null,
            'years_ago' => 5,
            'url' => null,
            'file' => null,
            'fr' => [
                'title' => 'Jeunes géométries',
                'description' => 'Sa première exposition collective, avec trois petits formats peints d’après ses tout premiers programmes.',
            ],
            'en' => [
                'title' => 'Young Geometries',
                'description' => 'Her first group exhibition, with three small paintings made after her very first programs.',
            ],
        ],
    ],
];
