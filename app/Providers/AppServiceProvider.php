<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $frontendUrl = config('kfms.frontend_url');

        if ($frontendUrl) {
            \Illuminate\Auth\Notifications\ResetPassword::toMailUsing(
                function ($notifiable, string $token) use ($frontendUrl) {
                    $resetUrl = rtrim($frontendUrl, '/')
                        . '/reset-password?token='
                        . rawurlencode($token)
                        . '&email='
                        . rawurlencode($notifiable->getEmailForPasswordReset());

                    $minutes = (int) config(
                        'auth.passwords.users.expire',
                        60
                    );

                    return (new \Illuminate\Notifications\Messages\MailMessage)
                        ->from(
                            config('mail.from.address'),
                            'KINVEST Fleet Management'
                        )
                        ->subject('Reset Your Password | KINVEST')
                        ->view('emails.auth.password-reset', [
                            'resetUrl' => $resetUrl,
                            'recipientName' => $notifiable->name ?: 'Team Member',
                            'expiryMinutes' => $minutes,
                        ]);
                }
            );

            \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(
                function ($user, string $token) use ($frontendUrl) {
                    return rtrim($frontendUrl, '/') .
                        '/reset-password?token=' .
                        rawurlencode($token) .
                        '&email=' .
                        rawurlencode($user->getEmailForPasswordReset());
                }
            );
        }

        //
    }
}
