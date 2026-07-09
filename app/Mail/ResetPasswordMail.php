<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $token;
    public string $email;
    public string $resetUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(string $token, string $email)
    {
        $this->token = $token;
        $this->email = $email;
        // Vite default dev port is 5173
        $this->resetUrl = 'http://localhost:5173/reset-password?token=' . urlencode($token) . '&email=' . urlencode($email);
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Reset Password Akun Carikan Amikom')
                    ->view('emails.reset-password');
    }
}
