<?php

namespace App\Livewire\Activity;

use Livewire\Component;
use App\Models\Lead;
use App\Models\Booking;
use App\Models\PaymentReceived;
use App\Models\ProjectUnit;

class PaymentForm extends Component
{
    public Lead $lead;

    public ?int $bookingId = null;
    public float $amount = 250000.00;
    public string $paymentMethod = 'bank_transfer';
    public string $transactionReference = '';
    public string $notes = '';

    public function mount(Lead $lead): void
    {
        $this->lead = $lead;
        $booking = Booking::where('lead_id', $this->lead->id)->latest('id')->first();
        $this->bookingId = $booking?->id;
        $this->transactionReference = 'TXN-' . strtoupper(substr(md5(microtime()), 0, 8));
    }

    public function recordPayment(): void
    {
        $this->validate([
            'amount' => 'required|numeric|min:1',
            'paymentMethod' => 'required',
        ]);

        $booking = Booking::find($this->bookingId);

        PaymentReceived::create([
            'booking_id' => $this->bookingId,
            'amount' => $this->amount,
            'method' => $this->paymentMethod,
            'transaction_id' => $this->transactionReference,
            'paid_at' => now(),
            'status' => 'completed',
        ]);

        $totalPaid = PaymentReceived::where('booking_id', $this->bookingId)->sum('amount');
        $targetAmount = $booking?->booking_amount ?? 500000.00;

        if ($totalPaid >= $targetAmount) {
            $this->lead->update(['current_stage' => 'Closed Won', 'status' => 'closed_won']);
            if ($booking?->project_unit_id) {
                ProjectUnit::where('id', $booking->project_unit_id)->update(['status' => 'sold']);
            }
            $this->dispatch('toast', type: 'success', message: "Payment recorded! Target booking amount reached — lead transitioned to Closed Won & Unit marked SOLD.");
        } else {
            $this->lead->update(['current_stage' => 'Payment', 'status' => 'payment']);
            $this->dispatch('toast', type: 'success', message: "Payment recorded! Stage set to Payment. Total paid: ₹" . number_format($totalPaid));
        }
    }

    public function render()
    {
        $bookings = Booking::where('lead_id', $this->lead->id)->get();
        $payments = PaymentReceived::whereIn('booking_id', $bookings->pluck('id'))->latest('id')->get();

        return view('livewire.activity.payment-form', [
            'bookings' => $bookings,
            'payments' => $payments,
        ]);
    }
}
