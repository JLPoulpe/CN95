<?php

namespace App\Mail;

use App\Entity\PlanningCreneau;
use App\Entity\User;

/** Prévient de l'absence d'un moniteur : ses collègues du groupe et le bureau, une seule fois par personne. */
class AbsenceMailer
{
    public function __construct(
        private readonly MailSender $sender,
        private readonly MailRenderer $renderer,
    ) {
    }

    /**
     * @param list<User> $attendus moniteurs attendus sur le créneau (groupe)
     * @param list<User> $bureau   membres du bureau
     *
     * @return int nombre de mails envoyés
     */
    public function absenceDeclaree(PlanningCreneau $creneau, User $absent, array $attendus, array $bureau): int
    {
        $envoyes = 0;
        $date = $creneau->getDate();
        $contenu = $this->renderer->rendre('mail/absence_moniteur.html.twig', [
            'absent' => $absent->getNomComplet(),
            'activite' => $creneau->getActivitesLabel(),
            'jour' => $creneau->getJour()?->label(),
            'date' => $date?->format('d/m/Y'),
            'lieux' => $creneau->getLieuxLabel(),
            'groupes' => implode(', ', array_map(static fn ($a) => (string) $a->getCode(), $creneau->getAptitudes()->toArray())),
        ]);
        $sujet = sprintf('Besoin d\'aide : %s absent(e) le %s', $absent->getNomComplet(), $date?->format('d/m/Y'));

        foreach (self::destinataires($absent, $attendus, $bureau) as $destinataire) {
            $this->sender->envoyer(new MailMessage((string) $destinataire->getEmail(), $destinataire->getNomComplet(), $sujet, $contenu));
            ++$envoyes;
        }

        return $envoyes;
    }

    /**
     * Moniteurs du groupe et bureau, sans doublon (un membre du bureau qui encadre le groupe ne reçoit qu'un mail),
     * sans l'absent et sans compte sans adresse mail.
     *
     * @param list<User> $attendus
     * @param list<User> $bureau
     *
     * @return list<User>
     */
    public static function destinataires(User $absent, array $attendus, array $bureau): array
    {
        $parId = [];
        foreach ([...$attendus, ...$bureau] as $user) {
            if ($user->getId() === $absent->getId() || '' === trim((string) $user->getEmail())) {
                continue;
            }
            $parId[$user->getId()] ??= $user;
        }

        return array_values($parId);
    }
}
