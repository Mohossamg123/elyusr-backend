<?php

namespace App\Notifications;

class DonationPaidNotification extends AppNotification
{
    public function __construct(
        public int $donationId,
        public string $donorName,
        public string $amount,
        public string $method
    ) {
    }

    protected function type(): string
    {
        return 'donation_paid';
    }

    protected function title(): string
    {
        return 'تم استلام تبرع مدفوع';
    }

    protected function message(): string
    {
        return "{$this->donorName} اتبرع بـ {$this->amount} جنيه — الدفع اتأكد";
    }

    protected function url(): ?string
    {
        return "/admin/donations/{$this->donationId}";
    }

    protected function severity(): string
    {
        return 'success';
    }

    protected function extra(): array
    {
        return ['donation_id' => $this->donationId, 'payment_method' => $this->method];
    }
}
