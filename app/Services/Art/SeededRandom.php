<?php

namespace App\Services\Art;

/**
 * Deterministic pseudo-random stream derived from a string seed with SHA-256
 * (never PHP's mt_rand state), so a composition is identical on every server.
 */
final class SeededRandom
{
    private int $block = 0;

    private string $pool = '';

    public function __construct(private readonly string $seed) {}

    /** A float in [0, 1). */
    public function float(): float
    {
        if (strlen($this->pool) < 4) {
            $this->pool .= hash('sha256', $this->seed."\0".$this->block++, true);
        }

        $value = unpack('N', substr($this->pool, 0, 4))[1];
        $this->pool = substr($this->pool, 4);

        return $value / 4294967296;
    }

    public function between(float $min, float $max): float
    {
        return $min + ($max - $min) * $this->float();
    }

    /** An integer in [$min, $max]. */
    public function int(int $min, int $max): int
    {
        return min($max, $min + (int) floor($this->float() * ($max - $min + 1)));
    }

    public function chance(float $probability): bool
    {
        return $this->float() < $probability;
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return T
     */
    public function pick(array $items): mixed
    {
        return $items[$this->int(0, count($items) - 1)];
    }

    /**
     * Fisher–Yates shuffle.
     *
     * @template T
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    public function shuffle(array $items): array
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = $this->int(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return $items;
    }
}
