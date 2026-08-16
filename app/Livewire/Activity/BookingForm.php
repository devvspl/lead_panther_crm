<?php

namespace App\Livewire\Activity;

use Livewire\Component;
use App\Models\Lead;
use App\Models\Proposal;
use App\Models\ProjectUnit;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Throwable;

class BookingForm extends Component
{
    public Lead $lead;

    public ?int $projectUnitId = null;
    public float $bookingAmount = 500000.00;

    public function mount(Lead $lead): void
    {
        $this->lead = $lead;

        $lastProposal = Proposal::where('lead_id', $this->lead->id)->latest('id')->first();
        if ($lastProposal) {
            $this->projectUnitId = $lastProposal->project_unit_id;
            $netPrice = $lastProposal->price - $lastProposal->discount;
            $this->bookingAmount = round($netPrice * 0.10, 2);
        } else {
            $firstUnit = ProjectUnit::where('project_id', $this->lead->project_id)->first();
            $this->projectUnitId = $firstUnit?->id;
        }
    }

    public function convertToBooking(): void
    {
        $this->validate([
            'projectUnitId' => 'required',
            'bookingAmount' => 'required|numeric|min:1000',
        ]);

        try {
            DB::transaction(function () {
                $unit = ProjectUnit::where('id', $this->projectUnitId)->lockForUpdate()->first();

                if ($unit && (strtolower($unit->status ?? '') === 'reserved' || strtolower($unit->status ?? '') === 'sold')) {
                    throw new \Exception("Double-booking prevented! Unit #{$unit->unit_number} is already reserved by another client.");
                }

                if ($unit) {
                    $unit->update(['status' => 'reserved']);
                }

                Booking::create([
                    'lead_id' => $this->lead->id,
                    'project_unit_id' => $this->projectUnitId,
                    'booking_amount' => $this->bookingAmount,
                    'booked_at' => now(),
                    'status' => 'confirmed',
                ]);

                $this->lead->update(['current_stage' => 'Booking', 'status' => 'booking']);
            });

            $this->dispatch('toast', type: 'success', message: 'Lead successfully converted to Booking & Unit locked in database.');
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function render()
    {
        $units = ProjectUnit::where('project_id', $this->lead->project_id)->get();
        if ($units->isEmpty()) {
            $units = ProjectUnit::all();
        }

        return view('livewire.activity.booking-form', [
            'units' => $units,
        ]);
    }
}
