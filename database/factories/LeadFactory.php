<?php

namespace Database\Factories;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        // Realistic Stage Distribution
        $rand = fake()->numberBetween(1, 100);
        if ($rand <= 15) {
            $stage = fake()->randomElement(['new', 'assigned']);
        } elseif ($rand <= 75) {
            $stage = fake()->randomElement(['contacted', 'in_progress', 'qualified', 'interested', 'site_visit', 'meeting', 'negotiation']);
        } else {
            $stage = fake()->randomElement(['closed_won', 'closed_lost', 'replaced', 'rejected']);
        }

        $bookingStatus = ($stage === 'closed_won') ? 'confirmed' : 'pending';
        $replacementStatus = ($stage === 'replaced') ? 'approved' : 'none';

        static $sequence = 1000;
        $sequence++;

        return [
            'lead_code' => 'LP-' . date('Y') . '-' . str_pad($sequence, 8, '0', STR_PAD_LEFT),
            'client_id' => null,
            'project_id' => null,
            'campaign_id' => null,
            'lead_source_id' => null,
            'name' => fake()->name(),
            'mobile' => fake()->numerify('+919#########'),
            'email' => fake()->safeEmail(),
            'city' => fake()->randomElement(['Bengaluru', 'Mumbai', 'Delhi NCR', 'Pune', 'Hyderabad', 'Chennai']),
            'location' => fake()->randomElement(['Whitefield', 'Indiranagar', 'Koramangala', 'Bandra', 'Gachibowli', 'HSR Layout']),
            'budget' => fake()->randomElement([4500000.00, 7500000.00, 12000000.00, 18000000.00, 25000000.00]),
            'property_type' => fake()->randomElement(['2 BHK', '3 BHK', '4 BHK Villa', 'Penthouse', 'Plot']),
            'requirement' => fake()->sentence(),
            'status' => $stage,
            'current_stage' => $stage,
            'lead_score' => fake()->numberBetween(30, 99),
            'assigned_to' => null,
            'first_response_at' => in_array($stage, ['new']) ? null : fake()->dateTimeBetween('-15 days', 'now'),
            'booking_status' => $bookingStatus,
            'replacement_status' => $replacementStatus,
            'created_at' => fake()->dateTimeBetween('-60 days', 'now'),
        ];
    }
}
