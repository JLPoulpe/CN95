<?php

namespace App\Mail;

use App\Enum\TypeMailTemplate;

/**
 * Contenus initiaux des mails, repris des anciens fichiers templates/mail/**.
 * Utilisés par app:seed pour recréer un template manquant ; la migration qui a introduit les templates en contient une copie.
 */
final class MailTemplateDefaults
{
    /** @return list<array{nom: string, type: TypeMailTemplate, contenu: string}> */
    public static function tous(): array
    {
        return [
            [
                'nom' => 'Invitation par défaut',
                'type' => TypeMailTemplate::Invitation,
                'contenu' => <<<'HTML'
<p>Vous êtes invité(e) à l'activité <strong>{{activite}}</strong> du <strong>{{date}}</strong>.</p>
<p>Le rendez-vous est fixé à 19h30 sur place pour être à 20h00 dans l’eau.</p>
<p>Le nombre de participants est toujours limité à 30, alors ne tardez pas à vous inscrire.</p>
<p>Les mineurs devront présenter une autorisation parentale.</p>
<p>Les documents seront vérifiés avant la constitution des palanquées</p>
<p><strong>⚠ Pas de documents = pas de plongée ⚠</strong></p>
<p>Si vous souhaitez faire du covoiturage faites-le nous savoir (départ 19h00 de la piscine).</p>
<p>À respecter avant d’accéder à la fosse :</p>
<ul>
    <li>Déchaussage obligatoire avant l'entrée dans les vestiaires.</li>
    <li>Passage cabine obligatoire pour se changer.</li>
    <li>Douche savonnée obligatoire pour tous avant l'accès au bassin.</li>
</ul>
<p>Nous comptons sur vous afin de respecter les conditions d'accès et d'hygiène données par AQUA 92 en faisant preuve d'organisation et de discipline.</p>
<p>A vos palmes, prêts, plongez ! Pour le Comité Directeur, Olivier, responsable de la communication.</p>
<p>Adresse : 119 Boulevard Charles de Gaulle - 92390 Villeneuve-la-Garenne<br>
<a href="https://aqua92.ucpa.com/">https://aqua92.ucpa.com/</a></p>
HTML,
            ],
            [
                'nom' => 'Annulation',
                'type' => TypeMailTemplate::Annulation,
                'contenu' => <<<'HTML'
<p>L'activité <strong>{{activite}}</strong> du <strong>{{date}}</strong> est annulée.</p>
HTML,
            ],
            [
                'nom' => 'Désistement (responsable)',
                'type' => TypeMailTemplate::DesistementResponsable,
                'contenu' => <<<'HTML'
<p><strong>{{membre}}</strong>, inscrit(e) à l'activité <strong>{{activite}}</strong> du <strong>{{date}}</strong>, ne pourra finalement pas participer.</p>
HTML,
            ],
            [
                'nom' => 'Activation de compte',
                'type' => TypeMailTemplate::ActivationCompte,
                'contenu' => <<<'HTML'
<p>Ce lien est valable 7 jours. Passé ce délai, demandez à un administrateur de vous en renvoyer un.</p>
HTML,
            ],
        ];
    }
}
