<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendOtpMail extends Mailable
{
   use Queueable, SerializesModels;

    public $code;

    // تمرير الكود الرقمي للرسالة
    public function __construct($code)
    {
        $this->code = $code;
    }

    // تحديد عنوان الرسالة الإيميل
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'رمز التحقق من الحساب - Haven Syria',
        );
    }

    // تحديد محتوى الرسالة (HTML)
    public function content(): Content
    {
        return new Content(
            htmlString: "<h3>مرحباً بك في Haven Syria</h3><p>رمز التحقق الخاص بحسابك هو: <b style='font-size: 20px; color: #2c3e50;'>{$this->code}</b></p><p>هذا الرمز صالح لمدة 10 دقائق فقط.</p>",
        );
    }
}
