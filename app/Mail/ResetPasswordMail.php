<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
  use Queueable, SerializesModels;

    public $code;

    public function __construct($code)
    {
        $this->code = $code;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'طلب إعادة تعيين كلمة المرور - Haven Syria',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "<h3>أهلاً بك في Haven Syria</h3><p>لقد استلمنا طلباً لإعادة تعيين كلمة المرور الخاصة بحسابك.</p><p>رمز التحقق (OTP) الخاص بك هو: <b style='font-size: 20px; color: #e74c3c;'>{$this->code}</b></p><p>إذا لم تكن أنت من طلب هذا، يمكنك تجاهل هذا الإيميل بأمان.</p>",
        );
    }
}
