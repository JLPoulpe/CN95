<?php

namespace App\Mail;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;

/**
 * Envoie la file des mails une fois la réponse HTTP partie (kernel.terminate), ou à la fin d'une commande de la console
 * (console.terminate) : pas de worker permanent ni de cron dédié. Le traitement s'arrête dès que la file est vide.
 * Un mail en échec reste en file et est repris à la prochaine requête ou commande qui ajoute un mail.
 */
final class EnvoiFileSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MailSender $sender,
        #[Autowire(service: 'messenger.transport.async')] private readonly ReceiverInterface $file,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onTerminate',
            ConsoleEvents::TERMINATE => 'onTerminate',
        ];
    }

    public function onTerminate(): void
    {
        if (!$this->sender->aEnvoisEnAttente()) {
            return;
        }

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $evenement): void {
            if ($evenement->isWorkerIdle()) {
                $evenement->getWorker()->stop();
            }
        });

        (new Worker(['async' => $this->file], $this->bus, $dispatcher))->run(['time_limit' => 120]);
    }
}
