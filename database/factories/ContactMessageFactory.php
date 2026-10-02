<?php

namespace Database\Factories;

use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'service' => null,
            'budget' => fake()->optional()->randomElement((array) config('atelier.budgets', [])),
            'message' => fake()->paragraphs(2, true),
            'locale' => 'fr',
            'ip_hash' => ContactMessage::hashIp(fake()->ipv4()),
            'user_agent' => fake()->userAgent(),
            'read_at' => null,
        ];
    }

    /** A request about a given service slug. */
    public function forService(string $slug): static
    {
        return $this->state(fn (): array => ['service' => $slug]);
    }

    /** Already read by the atelier. */
    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }

    /** Written by an English-speaking visitor. */
    public function english(): static
    {
        return $this->state(fn (): array => ['locale' => 'en']);
    }
}
