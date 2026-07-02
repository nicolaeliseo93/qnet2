<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(CanResetPassword $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Reset your password'))
            ->view(
                ['emails.reset-password', 'emails.reset-password-plain'],
                [
                    'appName' => config('app.name'),
                    'name' => $notifiable->getAttribute('name'),
                    'url' => $this->resetUrl($notifiable),
                    'expireMinutes' => (int) config('auth.passwords.users.expire', 60),
                ],
            );
    }

    /** Builds the reset link that opens the SPA frontend, not the backend. */
    private function resetUrl(CanResetPassword $notifiable): string
    {
        $query = http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return rtrim((string) config('app.frontend_url'), '/')."/reset-password?{$query}";
    }
}
