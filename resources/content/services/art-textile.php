<?php

// Service n°14 — Art textile & peinture sur tissu (docs/ARCHITECTURE.md §4).
// French is the main language; English is its translation. Edit the copy here.

return [
    'slug' => 'art-textile',
    'order' => 14,
    'category' => 'matiere',
    'accent' => 'red',
    'icon' => 'thread',
    'art_style' => 'tissage',
    'scene' => 'art-textile',
    'fr' => [
        'title' => 'Art textile & peinture sur tissu',
        'short' => 'Bannières, drapeaux, vêtements peints et tentures : la couleur de l’atelier sur le tissu.',
        'tagline' => 'Des étoffes qui portent vos couleurs et votre histoire.',
        'intro' => 'Le tissu bouge, se porte, se déploie au vent : c’est un support vivant. L’atelier peint et décore bannières de procession, drapeaux d’associations, tentures murales, nappes de cérémonie, costumes de spectacle et vêtements personnalisés. Des pièces uniques, peintes ou appliquées à la main, pensées pour être vues et utilisées.',
        'body' => [
            'Nous choisissons ensemble le tissu, les peintures textiles et les techniques : peinture à main levée, pochoir, appliqué cousu ou broderie simple. Le motif tient compte du mouvement de l’étoffe, de la distance à laquelle elle sera vue et de son entretien, pour qu’elle reste belle lavage après lavage.',
            'Une bannière ou un drapeau rassemble une communauté autour d’un symbole. Pour un spectacle, des costumes peints donnent une identité forte à une troupe. Nous accompagnons aussi les groupes qui souhaitent créer ensemble une tenture ou une grande pièce collective.',
        ],
        'features' => [
            ['title' => 'Bannières & drapeaux', 'text' => 'Des étendards pour associations, chorales, processions et événements.'],
            ['title' => 'Vêtements peints', 'text' => 'Vestes, tee-shirts et tenues décorés à la main, pièces uniques.'],
            ['title' => 'Costumes de scène', 'text' => 'Des costumes qui donnent une identité visuelle forte à une troupe.'],
            ['title' => 'Tentures murales', 'text' => 'De grandes pièces textiles qui adoucissent et colorent un espace.'],
            ['title' => 'Techniques mixtes', 'text' => 'Peinture, pochoir, appliqué cousu et petite broderie.'],
            ['title' => 'Tenue au lavage', 'text' => 'Peintures textiles fixées et conseils d’entretien précis.'],
        ],
        'process' => [
            ['title' => 'Le projet', 'text' => 'Usage, taille, tissu et symbole à représenter.'],
            ['title' => 'Le dessin', 'text' => 'Maquette en couleurs à l’échelle de la pièce.'],
            ['title' => 'La réalisation', 'text' => 'Peinture, découpe, couture et fixation des couleurs.'],
            ['title' => 'Les finitions', 'text' => 'Ourlets, fourreaux, œillets ou hampes selon l’usage.'],
        ],
        'ideal_for' => [
            'Associations, chorales et fanfares',
            'Paroisses et processions',
            'Troupes de théâtre et de danse',
            'Particuliers et cadeaux',
        ],
        'faq' => [
            [
                'q' => 'La peinture tient-elle au lavage ?',
                'a' => 'Oui, avec des peintures textiles fixées selon les règles. Nous vous remettons des conseils de lavage adaptés à chaque pièce.',
            ],
            [
                'q' => 'Pouvez-vous fabriquer une bannière complète ?',
                'a' => 'Oui : tissu, décor, ourlets, fourreau pour la hampe et finitions. Nous pouvons aussi décorer une bannière que vous possédez déjà.',
            ],
            [
                'q' => 'Réalisez-vous des séries de vêtements ?',
                'a' => 'Pour des pièces uniques ou de petites séries peintes à la main, oui. Pour de plus grandes quantités, notre service de sérigraphie est plus adapté.',
            ],
            ['q' => 'Peut-on créer une tenture collective ?', 'a' => 'Oui. Chaque participant réalise un morceau, puis l’atelier assemble l’ensemble en une grande pièce harmonieuse.'],
        ],
        'scene_alt' => 'Une bannière ondule au vent tandis que des bandes rouges, jaunes et bleues s’y peignent et qu’une aiguille coud un triangle.',
        'meta_description' => 'Art textile et peinture sur tissu : bannières, drapeaux, costumes, vêtements peints et tentures murales, pièces uniques ou collectives. Devis gratuit.',
    ],
    'en' => [
        'title' => 'Textile art & fabric painting',
        'short' => 'Banners, flags, painted garments and wall hangings: the atelier’s colour on fabric.',
        'tagline' => 'Fabrics that carry your colours and your story.',
        'intro' => 'Fabric moves, is worn, unfurls in the wind: it is a living surface. The atelier paints and decorates procession banners, association flags, wall hangings, ceremonial cloths, stage costumes and personalised garments. Unique pieces, painted or appliquéd by hand, designed to be seen and used.',
        'body' => [
            'Together we choose the fabric, the textile paints and the techniques: freehand painting, stencil, sewn appliqué or simple embroidery. The design takes into account the movement of the cloth, the distance from which it will be seen and its care, so it stays beautiful wash after wash.',
            'A banner or a flag gathers a community around a symbol. For a show, painted costumes give a troupe a strong identity. We also support groups who want to create a hanging or a large collective piece together.',
        ],
        'features' => [
            ['title' => 'Banners & flags', 'text' => 'Standards for associations, choirs, processions and events.'],
            ['title' => 'Painted garments', 'text' => 'Jackets, T-shirts and outfits decorated by hand, one of a kind.'],
            ['title' => 'Stage costumes', 'text' => 'Costumes that give a troupe a strong visual identity.'],
            ['title' => 'Wall hangings', 'text' => 'Large textile pieces that soften and colour a space.'],
            ['title' => 'Mixed techniques', 'text' => 'Painting, stencil, sewn appliqué and light embroidery.'],
            ['title' => 'Wash-proof', 'text' => 'Properly fixed textile paints and precise care instructions.'],
        ],
        'process' => [
            ['title' => 'The project', 'text' => 'Use, size, fabric and the symbol to depict.'],
            ['title' => 'The drawing', 'text' => 'A colour mock-up at the scale of the piece.'],
            ['title' => 'The making', 'text' => 'Painting, cutting, sewing and fixing the colours.'],
            ['title' => 'The finishing', 'text' => 'Hems, pole sleeves, eyelets or poles, depending on use.'],
        ],
        'ideal_for' => [
            'Associations, choirs and brass bands',
            'Parishes and processions',
            'Theatre and dance companies',
            'Individuals and gifts',
        ],
        'faq' => [
            ['q' => 'Does the paint survive washing?', 'a' => 'Yes, with textile paints fixed according to the rules. We give you washing advice suited to each piece.'],
            ['q' => 'Can you make a complete banner?', 'a' => 'Yes: fabric, decoration, hems, a sleeve for the pole and finishing. We can also decorate a banner you already own.'],
            [
                'q' => 'Do you make garments in series?',
                'a' => 'For unique pieces or small hand-painted series, yes. For larger quantities, our screen-printing service is better suited.',
            ],
            ['q' => 'Can we create a collective hanging?', 'a' => 'Yes. Each participant makes a piece, then the atelier assembles them into one large, harmonious work.'],
        ],
        'scene_alt' => 'A banner ripples in the wind while red, yellow and blue bands paint themselves on it and a needle stitches a triangle.',
        'meta_description' => 'Textile art and fabric painting: banners, flags, costumes, painted garments and wall hangings, unique or collective pieces. Free quote.',
    ],
];
