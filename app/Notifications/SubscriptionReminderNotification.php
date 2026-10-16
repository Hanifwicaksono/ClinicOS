<?php

namespace App\Notifications;

use App\Models\SubscriptionReminder;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionReminderNotification extends Notification
{
    public function __construct(public SubscriptionReminder $reminder) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $period = $this->reminder->period;
        $expiryDate = $this->reminder->due_on->translatedFormat('d M Y');
        $message = $this->reminder->milestone_days === 0
            ? "Paket {$period->plan_name} untuk {$this->reminder->clinic->name} telah kedaluwarsa."
            : "Paket {$period->plan_name} untuk {$this->reminder->clinic->name} akan berakhir dalam {$this->reminder->milestone_days} hari ({$expiryDate}).";

        return (new MailMessage)
            ->subject('Pengingat subscription ClinicOS')
            ->greeting('Halo '.$notifiable->name.',')
            ->line($message)
            ->line('Perpanjang paket agar operasional klinik tetap dapat ditulis. Data medis tetap aman dan dapat dilihat setelah paket berakhir.')
            ->action('Lihat paket', route('onboarding.plans.index'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $period = $this->reminder->period;
        $expired = $this->reminder->milestone_days === 0;

        return [
            'title' => $expired ? 'Subscription telah berakhir' : 'Subscription segera berakhir',
            'message' => $expired
                ? "Paket {$period->plan_name} telah kedaluwarsa. Perpanjang untuk membuka kembali operasional klinik."
                : "Paket {$period->plan_name} akan berakhir dalam {$this->reminder->milestone_days} hari.",
            'status' => $expired ? 'expired' : 'expiring',
            'subscription_period_id' => $period->id,
            'url' => route('onboarding.plans.index', absolute: false),
        ];
    }
}
