<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Advertisements\Enums\AdvertisementEventType;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Advertisements\Support\EventModel;

/** @extends Factory<AdvertisementEvent> */
final class AdvertisementEventFactory extends Factory
{
    protected $model = AdvertisementEvent::class;

    /**
     * Build the configured model, not the packaged one: a host that swaps
     * `advertisements.event_model` gets its own class, with its casts,
     * events and observers.
     *
     * @return class-string<AdvertisementEvent>
     */
    public function modelName(): string
    {
        return EventModel::class();
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'advertisement_id' => Advertisement::factory(),
            'type' => fake()->randomElement(AdvertisementEventType::cases()),
            'occurred_at' => now(),
        ];
    }

    public function impression(): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => AdvertisementEventType::Impression,
        ]);
    }

    public function click(): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => AdvertisementEventType::Click,
        ]);
    }
}
