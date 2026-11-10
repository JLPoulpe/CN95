<?php

namespace App\Tests\Unit\Mail;

use App\Mail\EnvoiFileSubscriber;
use App\Mail\MailSender;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;

class EnvoiFileSubscriberTest extends TestCase
{
    /** Avec un transport sync, le mail est déjà parti au dispatch : la fin de commande ne doit pas tenter de le lire. */
    public function testTransportSynchroneNeLeveRienALaFinDeCommande(): void
    {
        $sender = $this->createStub(MailSender::class);
        $sender->method('aEnvoisEnAttente')->willReturn(true);
        $bus = $this->createStub(MessageBusInterface::class);

        $subscriber = new EnvoiFileSubscriber($sender, new SyncTransport($bus), $bus);
        $subscriber->onTerminate();

        $this->addToAssertionCount(1);
    }
}
