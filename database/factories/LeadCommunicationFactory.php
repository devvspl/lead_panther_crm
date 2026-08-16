<?php

namespace Database\Factories;

use App\Models\LeadCommunication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadCommunication>
 */
class LeadCommunicationFactory extends Factory
{
    protected $model = LeadCommunication::class;

    public function definition(): array
    {
        $channel = fake()->randomElement(['crm', 'whatsapp', 'sms', 'email', 'push']);
        $sentAt = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'lead_id' => null,
            'client_id' => null,
            'user_id' => null,
            'channel' => $channel,
            'template' => 'template_' . fake()->word(),
            'message' => fake()->paragraph(),
            'status' => 'sent',
            'sent_at' => $sentAt,
            'delivered_at' => (clone $sentAt)->modify('+2 minutes'),
            'failed_at' => null,
            'created_at' => $sentAt,
        ];
    }
}
