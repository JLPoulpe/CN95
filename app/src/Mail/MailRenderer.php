<?php

namespace App\Mail;

use Twig\Environment;

/** Rend le contenu d'un mail (un gabarit de templates/mail/) puis l'enveloppe dans la mise en page commune. */
class MailRenderer
{
    public function __construct(private readonly Environment $twig)
    {
    }

    /** @param array<string, mixed> $contexte */
    public function rendre(string $gabarit, array $contexte = []): string
    {
        return $this->twig->render('mail/_base.html.twig', ['contenu' => $this->twig->render($gabarit, $contexte)]);
    }
}
