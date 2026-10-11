<?php

namespace App\MessageHandler;

use App\Mail\MailConfigurationManager;
use App\Mail\MailException;
use App\Mail\Plugin\MailPluginRegistry;
use App\Message\SendMailMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendMailMessageHandler
{
    public function __construct(
        private readonly MailConfigurationManager $configurations,
        private readonly MailPluginRegistry $plugins,
    ) {
    }

    public function __invoke(SendMailMessage $message): void
    {
        $configuration = $this->configurations->courante()
            ?? throw new MailException('Aucun système d\'envoi de mails configuré.');

        $this->plugins->get($configuration->getPluginCode())
            ->send($message->mail, $this->configurations->configPlugin($configuration));
    }
}
