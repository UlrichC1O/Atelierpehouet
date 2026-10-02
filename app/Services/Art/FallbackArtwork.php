<?php

namespace App\Services\Art;

/**
 * Built-in SVG composition used when the Python art engine is unavailable.
 *
 * Deterministic for a (style, seed) pair: the logo's triangle "A" (canonical geometry,
 * docs/ARCHITECTURE.md §1) with its Mondrian colour fields permuted from a hash of
 * the seed and white seams, a style motif glowing out of the black canvas, a neon
 * TELIERS-gradient echo and baseline, triangle sparks, and the signature's long
 * diagonal slashes signing the lower-right corner. Logo palette only.
 */
final class FallbackArtwork
{
    private const CANVAS = 1000;

    /** Triangle pieces: logo colour ⇒ points (triangle 100 × 86.6). */
    private const PIECES = [
        'yellow' => '50,0 78.98,50.2 39.3,50.2 39.3,18.53',
        'blue' => '39.3,18.53 39.3,68.8 10.28,68.8',
        'white' => '39.3,50.2 59.3,50.2 59.3,86.6 54.8,86.6 54.8,68.8 39.3,68.8',
        'red' => '59.3,50.2 78.98,50.2 100,86.6 59.3,86.6',
        'black' => '10.28,68.8 54.8,68.8 54.8,86.6 0,86.6',
    ];

    private const OUTLINE = '50,0 100,86.6 0,86.6';

    /** Centroid of the triangle, the pivot of every transform. */
    private const PIVOT = [50.0, 57.733];

    /** Mirrors config('atelier.palette') — used when the config is unavailable. */
    private const PALETTE = [
        'black' => '#000000', 'ink' => '#08080b', 'coal' => '#121216', 'graphite' => '#1d1d23',
        'steel' => '#2c2c34', 'smoke' => '#898789', 'mist' => '#c4c2c5', 'white' => '#fafcfd',
        'blue' => '#265fa5', 'blue_bright' => '#4f8edc', 'blue_deep' => '#173d6b',
        'yellow' => '#f8d449', 'amber' => '#e3a94e', 'orange' => '#c96338',
        'red' => '#b32c2b', 'red_bright' => '#e8433b', 'red_deep' => '#6e1a1a',
    ];

    /** Colours used by motifs and sparks. */
    private const BRIGHTS = ['blue', 'yellow', 'red', 'amber', 'orange', 'white', 'blue_bright', 'red_bright'];

    /** Stained-glass panes: saturated colours only. */
    private const GLASS = ['blue', 'yellow', 'red', 'amber', 'orange', 'blue_bright', 'red_bright'];

    private readonly SeededRandom $rng;

    /** @var array<string, string> */
    private readonly array $palette;

    private readonly string $uid;

    private readonly float $scale;

    private readonly float $angle;

    private readonly float $cx;

    private readonly float $cy;

    public function __construct(
        private readonly string $style,
        private readonly string $seed,
        private readonly int $size = 800,
        private readonly bool $animate = false,
    ) {
        $configured = function_exists('config') ? (array) config('atelier.palette', []) : [];
        $this->palette = array_merge(self::PALETTE, array_filter($configured, 'is_string'));
        $this->rng = new SeededRandom('pehouet-fallback|'.$style.'|'.$seed);
        $this->uid = substr(hash('sha256', $style.'|'.$seed), 0, 8);

        $this->scale = $this->rng->between(5.3, 6.1);
        $this->angle = $this->rng->between(-6.0, 6.0);
        $this->cx = 500 + $this->rng->between(-45, 45);
        $this->cy = 540 + $this->rng->between(-25, 25);
    }

    public function toSvg(): string
    {
        $size = max(16, min(4000, $this->size));
        $title = self::escape('Ateliers Pehouet — '.$this->style.' — '.$this->seed);
        $fields = array_combine(array_keys(self::PIECES), $this->rng->shuffle(array_keys(self::PIECES)));
        $glow = $this->palette[$this->firstColour($fields)];

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.self::CANVAS.' '.self::CANVAS.'"'
            .' width="'.$size.'" height="'.$size.'" role="img" aria-labelledby="t-'.$this->uid.'">'
            .'<title id="t-'.$this->uid.'">'.$title.'</title>'
            .$this->defs($glow)
            .($this->animate ? $this->styles() : '')
            .'<rect width="'.self::CANVAS.'" height="'.self::CANVAS.'" fill="'.$this->palette['black'].'"/>'
            .'<circle class="ap-art-halo" cx="'.self::num($this->cx).'" cy="'.self::num($this->cy).'" r="560" fill="url(#h-'.$this->uid.')"/>'
            .'<g class="ap-art-fade">'.$this->motif().'</g>'
            .'<rect width="'.self::CANVAS.'" height="'.self::CANVAS.'" fill="url(#v-'.$this->uid.')"/>'
            .$this->echo()
            .$this->sparks(5)
            .$this->triangle($fields)
            .$this->baseline()
            .$this->slashes()
            .$this->sparks(4)
            .'</svg>';

        return $svg."\n";
    }

    /**
     * @param  array<string, string>  $fields  piece ⇒ colour
     */
    private function firstColour(array $fields): string
    {
        foreach ($fields as $colour) {
            if ($colour !== 'white' && $colour !== 'black') {
                return $colour;
            }
        }

        return 'red';
    }

    private function defs(string $glow): string
    {
        $p = $this->palette;
        $id = $this->uid;

        return '<defs>'
            .'<radialGradient id="h-'.$id.'"><stop offset="0" stop-color="'.$glow.'" stop-opacity=".42"/>'
            .'<stop offset=".55" stop-color="'.$glow.'" stop-opacity=".1"/><stop offset="1" stop-color="'.$glow.'" stop-opacity="0"/></radialGradient>'
            .'<radialGradient id="v-'.$id.'" r=".75"><stop offset=".45" stop-color="'.$p['black'].'" stop-opacity="0"/>'
            .'<stop offset="1" stop-color="'.$p['black'].'" stop-opacity=".85"/></radialGradient>'
            .'<linearGradient id="g-'.$id.'"><stop offset="0" stop-color="'.$p['yellow'].'"/><stop offset=".16" stop-color="'.$p['amber'].'"/>'
            .'<stop offset=".38" stop-color="'.$p['orange'].'"/><stop offset=".66" stop-color="'.$p['red_bright'].'"/>'
            .'<stop offset="1" stop-color="'.$p['red'].'"/></linearGradient>'
            .'<filter id="n-'.$id.'" x="-20%" y="-20%" width="140%" height="140%"><feGaussianBlur stdDeviation="7" result="b"/>'
            .'<feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>'
            .'</defs>';
    }

    /** Embedded animation (only with ?animate=1); honours prefers-reduced-motion. */
    private function styles(): string
    {
        return '<style>'
            .'.ap-art-piece{transform-box:fill-box;transform-origin:50% 60%;animation:ap-art-rise 1.2s cubic-bezier(.16,1,.3,1) both}'
            .'.ap-art-slash{transform-box:fill-box;transform-origin:0 100%;animation:ap-art-slash 1s cubic-bezier(.65,0,.35,1) both}'
            .'.ap-art-echo{transform-box:fill-box;transform-origin:50% 66%;animation:ap-art-echo 2.2s cubic-bezier(.16,1,.3,1) both}'
            .'.ap-art-bar{transform-box:fill-box;transform-origin:0 50%;animation:ap-art-bar 1.4s .9s cubic-bezier(.16,1,.3,1) both}'
            .'.ap-art-fade{animation:ap-art-fade 2s ease-out both}'
            .'.ap-art-halo{animation:ap-art-breathe 7s ease-in-out infinite alternate}'
            .'.ap-art-spark{transform-box:fill-box;transform-origin:50% 50%;animation:ap-art-spark 4.8s ease-in-out infinite alternate}'
            .'@keyframes ap-art-rise{from{opacity:0;transform:translateY(60px) scale(.85)}to{opacity:1;transform:none}}'
            .'@keyframes ap-art-slash{from{opacity:0;transform:scale(0)}to{opacity:1;transform:none}}'
            .'@keyframes ap-art-echo{from{opacity:0;transform:scale(.82)}to{opacity:1;transform:none}}'
            .'@keyframes ap-art-bar{from{opacity:0;transform:scaleX(0)}to{opacity:1;transform:none}}'
            .'@keyframes ap-art-fade{from{opacity:0}to{opacity:1}}'
            .'@keyframes ap-art-breathe{from{opacity:.7}to{opacity:1}}'
            .'@keyframes ap-art-spark{from{opacity:.45;transform:scale(.8)}to{opacity:1;transform:none}}'
            .'@media (prefers-reduced-motion:reduce){.ap-art-piece,.ap-art-slash,.ap-art-echo,.ap-art-bar,.ap-art-fade,.ap-art-halo,.ap-art-spark{animation:none}}'
            .'</style>';
    }

    /**
     * The logo triangle with permuted colour fields and white seams.
     *
     * @param  array<string, string>  $fields
     */
    private function triangle(array $fields): string
    {
        $white = $this->palette['white'];
        $svg = '<g transform="'.$this->transform(1.0, 0.0).'" stroke="'.$white.'" stroke-linejoin="round">';
        $i = 0;

        foreach (self::PIECES as $piece => $points) {
            $svg .= '<polygon class="ap-art-piece" points="'.$points.'" fill="'.$this->palette[$fields[$piece]].'" stroke-width="1"'
                .$this->delay(0.1 + 0.09 * $i++).'/>';
        }

        return $svg.'<polygon class="ap-art-piece" points="'.self::OUTLINE.'" fill="none" stroke-width="1.6"'.$this->delay(0.55).'/></g>';
    }

    /** A neon TELIERS-gradient echo of the outline, with two faint white ones. */
    private function echo(): string
    {
        $neon = 1.16 + $this->rng->between(0, 0.1);
        $turn = $this->rng->between(-9, 9);
        $white = $this->palette['white'];

        $svg = '<g filter="url(#n-'.$this->uid.')" opacity=".75"><g transform="'.$this->transform($neon, $turn).'">'
            .'<polygon class="ap-art-echo" points="'.self::OUTLINE.'" fill="none" stroke="url(#g-'.$this->uid.')"'
            .' stroke-width="'.self::num(5 / ($this->scale * $neon)).'" stroke-linejoin="round"'.$this->delay(0.3).'/></g></g>';

        foreach ([[1.48, 0.16], [1.86, 0.08]] as $k => [$factor, $opacity]) {
            $svg .= '<g transform="'.$this->transform($factor, $turn * ($k + 2)).'">'
                .'<polygon class="ap-art-echo" points="'.self::OUTLINE.'" fill="none" stroke="'.$white.'" stroke-opacity="'.$opacity.'"'
                .' stroke-width="'.self::num(2.5 / ($this->scale * $factor)).'" stroke-linejoin="round"'.$this->delay(0.5 + 0.25 * $k).'/></g>';
        }

        return $svg;
    }

    /** A thin neon bar in the TELIERS gradient under the triangle. */
    private function baseline(): string
    {
        [$x1, $y1] = $this->project(0, 86.6);
        [$x2, $y2] = $this->project(100, 86.6);
        $y = max($y1, $y2) + 34;
        $x = min($x1, $x2) - 10;
        $width = abs($x2 - $x1) + $this->rng->between(60, 170);
        $rect = 'x="'.self::num($x).'" y="'.self::num($y).'" width="'.self::num($width).'" height="8" rx="4" fill="url(#g-'.$this->uid.')"';

        return '<g filter="url(#n-'.$this->uid.')"><rect class="ap-art-bar" '.$rect.'/></g>';
    }

    /** The signature: long thin diagonal slashes (≈ −20° and ≈ 65°) signing the lower right. */
    private function slashes(): string
    {
        $fx = 770 + $this->rng->between(-50, 40);
        $fy = 800 + $this->rng->between(-40, 50);
        $white = $this->palette['white'];
        $strokes = [
            [20, $this->rng->between(720, 900), $this->rng->between(4.5, 6.5), 0.62],
            [20, $this->rng->between(300, 420), $this->rng->between(3, 4.5), 0.5],
            [18, $this->rng->between(240, 340), $this->rng->between(2.5, 3.5), 0.45],
            [65, $this->rng->between(230, 320), $this->rng->between(3, 4.5), 0.5],
            [63, $this->rng->between(170, 260), $this->rng->between(2.5, 3.5), 0.42],
        ];

        $svg = '';

        foreach ($strokes as $i => [$degrees, $length, $width, $along]) {
            $theta = deg2rad($degrees + $this->rng->between(-3, 3));
            [$dx, $dy] = [cos($theta), -sin($theta)];
            $offset = $this->rng->between(-38, 38);
            $sx = $fx - $dx * $length * $along - $dy * $offset;
            $sy = $fy - $dy * $length * $along + $dx * $offset;

            $svg .= '<polygon class="ap-art-slash" points="'.$this->needle($sx, $sy, $dx, $dy, $length, $width).'" fill="'.$white.'"'
                .' fill-opacity="'.self::num($this->rng->between(0.78, 0.98)).'"'.$this->delay(1.0 + 0.12 * $i).'/>';
        }

        return $svg;
    }

    /** Small triangles drifting around the composition, away from the mark. */
    private function sparks(int $count): string
    {
        $svg = '';
        $radius = $this->scale * 62;

        for ($i = 0; $i < $count; $i++) {
            $attempts = 0;

            do {
                $x = $this->rng->between(60, 940);
                $y = $this->rng->between(60, 940);
            } while (hypot($x - $this->cx, $y - $this->cy) < $radius && ++$attempts < 24);

            $side = $this->rng->between(10, 30);
            $colour = $this->palette[$this->rng->pick(self::BRIGHTS)];
            $paint = $this->rng->chance(0.7)
                ? 'fill="'.$colour.'"'
                : 'fill="none" stroke="'.$colour.'" stroke-width="2" stroke-linejoin="round"';

            $svg .= '<g transform="translate('.self::num($x).' '.self::num($y).') rotate('.self::num($this->rng->between(0, 360)).')">'
                .'<polygon class="ap-art-spark" points="'.self::points([[0, -$side * 0.58], [$side / 2, $side * 0.29], [-$side / 2, $side * 0.29]]).'" '
                .$paint.' opacity="'.self::num($this->rng->between(0.55, 1)).'"'.$this->delay($this->rng->between(0, 3)).'/></g>';
        }

        return $svg;
    }

    /** Background motif, one per art style. */
    private function motif(): string
    {
        return match ($this->style) {
            'mondrian' => $this->mondrian(),
            'prisme' => $this->prisme(),
            'mosaique' => $this->mosaique(),
            'vitrail' => $this->vitrail(),
            'eclats' => $this->eclats(),
            'soleil' => $this->soleil(),
            'tissage' => $this->tissage(),
            default => $this->tessellation(),
        };
    }

    /** pehouet: a loose tessellation of logo triangles fading into the dark. */
    private function tessellation(): string
    {
        $svg = '';

        for ($i = 0; $i < 7; $i++) {
            $side = $this->rng->between(90, 260);
            $x = $this->rng->between(0, 1000);
            $y = $this->rng->between(0, 1000);
            $up = $this->rng->chance(0.5) ? 1 : -1;
            $svg .= '<polygon points="'.self::points([[$x, $y - $up * $side * 0.58], [$x + $side / 2, $y + $up * $side * 0.29], [$x - $side / 2, $y + $up * $side * 0.29]]).'"'
                .$this->tint(0.12, 0.26).' stroke="'.$this->palette['white'].'" stroke-opacity=".14" stroke-width="2"/>';
        }

        return $svg;
    }

    /** mondrian: a recursive rectangular partition with white seams. */
    private function mondrian(): string
    {
        $rects = [[0.0, 0.0, 1000.0, 1000.0]];

        for ($i = 0; $i < 8; $i++) {
            usort($rects, fn (array $a, array $b): int => $b[2] * $b[3] <=> $a[2] * $a[3]);
            [$x, $y, $w, $h] = array_shift($rects);
            $cut = $this->rng->between(0.3, 0.7);

            if ($w >= $h) {
                array_push($rects, [$x, $y, $w * $cut, $h], [$x + $w * $cut, $y, $w * (1 - $cut), $h]);
            } else {
                array_push($rects, [$x, $y, $w, $h * $cut], [$x, $y + $h * $cut, $w, $h * (1 - $cut)]);
            }
        }

        $svg = '';

        foreach ($rects as [$x, $y, $w, $h]) {
            $fill = $this->rng->chance(0.5) ? $this->tint(0.16, 0.3) : ' fill="none"';
            $svg .= '<rect x="'.self::num($x).'" y="'.self::num($y).'" width="'.self::num($w).'" height="'.self::num($h).'"'
                .$fill.' stroke="'.$this->palette['white'].'" stroke-opacity=".2" stroke-width="5"/>';
        }

        return $svg;
    }

    /** prisme: a large triangle subdivided recursively. */
    private function prisme(): string
    {
        $triangles = [];
        $split = function (array $a, array $b, array $c, int $depth) use (&$split, &$triangles): void {
            if ($depth === 0 || ($depth < 3 && $this->rng->chance(0.3))) {
                $triangles[] = [$a, $b, $c];

                return;
            }

            $ab = [($a[0] + $b[0]) / 2, ($a[1] + $b[1]) / 2];
            $bc = [($b[0] + $c[0]) / 2, ($b[1] + $c[1]) / 2];
            $ca = [($c[0] + $a[0]) / 2, ($c[1] + $a[1]) / 2];

            foreach ([[$a, $ab, $ca], [$ab, $b, $bc], [$ca, $bc, $c], [$ab, $bc, $ca]] as [$p, $q, $r]) {
                $split($p, $q, $r, $depth - 1);
            }
        };
        $split([500, -90], [1150, 1040], [-150, 1040], 3);

        $svg = '';

        foreach ($triangles as $triangle) {
            $fill = $this->rng->chance(0.38) ? $this->tint(0.12, 0.28) : ' fill="none"';
            $svg .= '<polygon points="'.self::points($triangle).'"'.$fill.' stroke="'.$this->palette['white'].'" stroke-opacity=".12" stroke-width="2"/>';
        }

        return $svg;
    }

    /** mosaique: a triangular lattice with scattered coloured tiles. */
    private function mosaique(): string
    {
        $side = 100;
        $height = 86.6;
        $svg = '';

        for ($row = 0; $row * $height < 1000; $row++) {
            for ($col = -1; $col * $side / 2 < 1000 + $side; $col++) {
                if (! $this->rng->chance(0.24)) {
                    continue;
                }

                $x = $col * $side / 2;
                $top = $row * $height;
                $points = ($row + $col) % 2 === 0
                    ? [[$x, $top + $height], [$x + $side / 2, $top], [$x + $side, $top + $height]]
                    : [[$x, $top], [$x + $side, $top], [$x + $side / 2, $top + $height]];

                $svg .= '<polygon points="'.self::points($points).'"'.$this->tint(0.18, 0.42)
                    .' stroke="'.$this->palette['white'].'" stroke-opacity=".3" stroke-width="3" stroke-linejoin="round"/>';
            }
        }

        return $svg;
    }

    /** vitrail: stained glass — translucent panes held by lead lines around a rose. */
    private function vitrail(): string
    {
        $ox = 500 + $this->rng->between(-180, 180);
        $oy = $this->rng->between(160, 340);
        $count = $this->rng->int(9, 13);
        $angles = [];

        for ($i = 0; $i < $count; $i++) {
            $angles[] = (360 / $count) * ($i + $this->rng->between(-0.3, 0.3));
        }

        $lead = $this->palette['steel'];
        $svg = '';

        foreach ($angles as $i => $start) {
            $end = $angles[($i + 1) % $count] + ($i + 1 === $count ? 360 : 0);
            $points = [[$ox, $oy], $this->polar($ox, $oy, 1600, $start), $this->polar($ox, $oy, 1600, $end)];
            $fill = $this->rng->chance(0.75) ? $this->tint(0.2, 0.38, self::GLASS) : ' fill="none"';
            $svg .= '<polygon points="'.self::points($points).'"'.$fill.' stroke="'.$lead.'" stroke-width="9" stroke-linejoin="round"/>';
        }

        foreach ([$this->rng->between(150, 210), $this->rng->between(330, 420)] as $radius) {
            $svg .= '<circle cx="'.self::num($ox).'" cy="'.self::num($oy).'" r="'.self::num($radius).'" fill="none" stroke="'.$lead.'" stroke-width="9"/>'
                .'<circle cx="'.self::num($ox).'" cy="'.self::num($oy).'" r="'.self::num($radius).'" fill="none" stroke="'.$this->palette['white'].'" stroke-opacity=".22" stroke-width="1.5"/>';
        }

        return $svg;
    }

    /** eclats: shards flying along the signature's angles. */
    private function eclats(): string
    {
        $svg = '';

        for ($i = 0; $i < 16; $i++) {
            $theta = deg2rad($this->rng->pick([20, 65, 200, 245]) + $this->rng->between(-6, 6));
            [$dx, $dy] = [cos($theta), -sin($theta)];
            $length = $this->rng->between(120, 460);
            $points = $this->needle($this->rng->between(0, 1000), $this->rng->between(0, 1000), $dx, $dy, $length, $this->rng->between(6, 22));
            $svg .= '<polygon points="'.$points.'"'.$this->tint(0.3, 0.7).'/>';
        }

        return $svg;
    }

    /** soleil: rays radiating from the apex of the triangle. */
    private function soleil(): string
    {
        [$ox, $oy] = $this->project(50, 0);
        $count = $this->rng->int(22, 32);
        $svg = '';

        for ($i = 0; $i < $count; $i++) {
            $center = (360 / $count) * $i + $this->rng->between(-2, 2);
            $half = $this->rng->between(1.2, 2.6);
            $points = [[$ox, $oy], $this->polar($ox, $oy, 1600, $center - $half), $this->polar($ox, $oy, 1600, $center + $half)];
            $svg .= '<polygon points="'.self::points($points).'"'.$this->tint(0.1, 0.26).'/>';
        }

        return $svg;
    }

    /** tissage: two families of bands woven across the canvas. */
    private function tissage(): string
    {
        $svg = '';

        // Both families rise to the right, like the signature (SVG rotation is clockwise).
        foreach ([-20 + $this->rng->between(-5, 5), -65 + $this->rng->between(-5, 5)] as $family => $degrees) {
            $svg .= '<g transform="rotate('.self::num($degrees).' 500 500)">';

            for ($offset = -700 + $this->rng->between(0, 60); $offset < 1700; $offset += $this->rng->between(110, 170)) {
                $svg .= '<rect x="-600" y="'.self::num($offset).'" width="2200" height="'.self::num($this->rng->between(34, 72)).'"'
                    .$this->tint($family === 0 ? 0.16 : 0.1, $family === 0 ? 0.3 : 0.2).'/>';
            }

            $svg .= '</g>';
        }

        return $svg;
    }

    /**
     * Fill attribute with a random palette colour at a random opacity.
     *
     * @param  list<string>  $colours
     */
    private function tint(float $min, float $max, array $colours = self::BRIGHTS): string
    {
        return ' fill="'.$this->palette[$this->rng->pick($colours)].'" fill-opacity="'.self::num($this->rng->between($min, $max)).'"';
    }

    /** A thin rhombus from ($x, $y) along ($dx, $dy), widest at a third of its length. */
    private function needle(float $x, float $y, float $dx, float $dy, float $length, float $width): string
    {
        [$nx, $ny] = [-$dy * $width / 2, $dx * $width / 2];
        [$mx, $my] = [$x + $dx * $length * 0.32, $y + $dy * $length * 0.32];

        return self::points([[$x, $y], [$mx + $nx, $my + $ny], [$x + $dx * $length, $y + $dy * $length], [$mx - $nx, $my - $ny]]);
    }

    /**
     * Canvas coordinates of a point of the triangle (triangle units).
     *
     * @return array{0: float, 1: float}
     */
    private function project(float $x, float $y): array
    {
        $dx = ($x - self::PIVOT[0]) * $this->scale;
        $dy = ($y - self::PIVOT[1]) * $this->scale;
        $r = deg2rad($this->angle);

        return [$this->cx + $dx * cos($r) - $dy * sin($r), $this->cy + $dx * sin($r) + $dy * cos($r)];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function polar(float $x, float $y, float $radius, float $degrees): array
    {
        $r = deg2rad($degrees);

        return [$x + cos($r) * $radius, $y + sin($r) * $radius];
    }

    /** Transform placing the triangle (scaled by $factor, turned by $turn degrees) on the canvas. */
    private function transform(float $factor, float $turn): string
    {
        return 'translate('.self::num($this->cx).' '.self::num($this->cy).') rotate('.self::num($this->angle + $turn).')'
            .' scale('.self::num($this->scale * $factor, 3).') translate(-50 -57.733)';
    }

    private function delay(float $seconds): string
    {
        return $this->animate ? ' style="animation-delay:'.self::num($seconds, 2).'s"' : '';
    }

    /**
     * @param  list<array{0: float|int, 1: float|int}>  $points
     */
    private static function points(array $points): string
    {
        return implode(' ', array_map(fn (array $p): string => self::num($p[0]).','.self::num($p[1]), $points));
    }

    private static function num(float|int $value, int $decimals = 1): string
    {
        $text = rtrim(rtrim(number_format((float) $value, $decimals, '.', ''), '0'), '.');

        return $text === '-0' || $text === '' ? '0' : $text;
    }

    /** Escape text for XML, dropping characters XML 1.0 forbids. */
    private static function escape(string $text): string
    {
        $text = mb_scrub($text, 'UTF-8');
        $text = (string) preg_replace('/[\x{0}-\x{8}\x{B}\x{C}\x{E}-\x{1F}\x{7F}\x{FFFE}\x{FFFF}]/u', '', $text);

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
