<?php

namespace App\Message;

use App\Mail\MailMessage;

final readonly class SendMailMessage
{
    public function __construct(public MailMessage $mail)
    {
    }
}
