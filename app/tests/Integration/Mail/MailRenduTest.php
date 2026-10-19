<?php

namespace App\Tests\Integration\Mail;

use App\Entity\ActiviteExterne;
use App\Entity\Communication;
use App\Entity\InvitationFosse;
use App\Entity\ReponseFosse;
use App\Entity\User;
use App\Mail\CommunicationMailer;
use App\Mail\CompteMailer;
use App\Mail\ContenuMailSanitizer;
use App\Mail\FosseMailer;
use App\Mail\MailMessage;
use App\Mail\MailRenderer;
use App\Mail\MailSender;
use App\Mail\RgpdMailer;
use App\Message\SendMailMessage;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/** Rend chaque mail avec le vrai Twig de l'application ; un faux bus capture les messages, rien n'est envoyé. */
class MailRenduTest extends KernelTestCase
{
    private MailRenderer $renderer;
    private MailSender $sender;
    /** @var list<MailMessage> */
    private array $envoyes = [];

    protected function setUp(): void
    {
        self::bootKernel();

        $envoyes = &$this->envoyes;
        $bus = new class($envoyes) implements MessageBusInterface {
            /** @param list<MailMessage> $envoyes */
            public function __construct(private array &$envoyes)
            {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                \assert($message instanceof SendMailMessage);
                $this->envoyes[] = $message->mail;

                return new Envelope($message);
            }
        };

        $this->renderer = new MailRenderer(self::getContainer()->get('twig'));
        $this->sender = new MailSender($bus, new NullLogger());
    }

    private function membre(): User
    {
        return (new User())->setPrenom('Camille')->setNom('Martin')->setEmail('camille@example.test');
    }

    private function reponse(?string $commentaire = null, string $activite = 'Fosse'): ReponseFosse
    {
        $invitation = (new InvitationFosse())
            ->setActivite((new ActiviteExterne())->setLibelle($activite))
            ->setDate(new \DateTimeImmutable('2026-12-05'))
            ->setCommentaire($commentaire);

        return new ReponseFosse($invitation, $this->membre());
    }

    private function fosseMailer(): FosseMailer
    {
        return new FosseMailer($this->sender, $this->renderer, new ContenuMailSanitizer(), self::getContainer()->get('router'));
    }

    public function testActivationContientLeLienLInformationRgpdEtLesTypesDeMails(): void
    {
        (new CompteMailer($this->sender, $this->renderer))->activation($this->membre(), 'https://exemple.test/compte/activation/abc');

        $this->assertCount(1, $this->envoyes);
        $mail = $this->envoyes[0];
        $this->assertSame('camille@example.test', $mail->toEmail);
        $this->assertSame('Activation de votre compte CN95 Plongée', $mail->sujet);
        $this->assertStringContainsString('Bonjour à toutes et à tous', $mail->html, 'la base enveloppe le mail');
        $this->assertStringContainsString('href="https://exemple.test/compte/activation/abc"', $mail->html);
        $this->assertStringContainsString('règlement général sur la protection des données', $mail->html);
        $this->assertStringContainsString('Les mails que vous recevrez', $mail->html);
        $this->assertStringContainsString('communications officielles du bureau', $mail->html);
        $this->assertStringContainsString('valable 7 jours', $mail->html);
    }

    public function testInvitationEstLaBasePlusLeCommentairePlusLesTroisLiens(): void
    {
        $reponse = $this->reponse('<p>Rendez-vous à <strong>19h30</strong>.</p>');

        $this->fosseMailer()->invitation($reponse);

        $mail = $this->envoyes[0];
        $this->assertSame('Invitation : Fosse du 05/12/2026', $mail->sujet);
        $this->assertStringContainsString('Bonjour à toutes et à tous', $mail->html);
        $this->assertStringContainsString('Rendez-vous à <strong>19h30</strong>', $mail->html);
        foreach (['inscription', 'refus', 'desistement'] as $choix) {
            $this->assertStringContainsString('/fosse/', $mail->html);
            $this->assertStringContainsString($reponse->getToken().'/'.$choix, $mail->html, $choix);
        }
        $this->assertLessThan(
            strpos($mail->html, 'indiquer votre choix'),
            strpos($mail->html, 'Rendez-vous à'),
            'le commentaire précède les liens',
        );
    }

    public function testRelanceChangeLObjet(): void
    {
        $this->fosseMailer()->invitation($this->reponse(), relance: true);

        $this->assertSame('Rappel : Fosse du 05/12/2026', $this->envoyes[0]->sujet);
    }

    public function testInvitationNettoieLeCommentaireEtEchappeLActivite(): void
    {
        $this->fosseMailer()->invitation($this->reponse('<p>Texte</p><script>alert(1)</script>', 'Fosse <b>&</b>'));

        $html = $this->envoyes[0]->html;
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('Texte', $html);
        $this->assertStringContainsString('Fosse &lt;b&gt;&amp;&lt;/b&gt;', $html);
    }

    public function testMailDEssaiDInvitationUtiliseDesLiensFactices(): void
    {
        $html = $this->fosseMailer()->htmlInvitation('Fosse', '05/12/2026', 'Un texte');

        $this->assertStringContainsString('href="#"', $html);
        $this->assertStringContainsString('Un texte', $html);
    }

    public function testAnnulationEtDesistement(): void
    {
        $reponse = $this->reponse();
        $mailer = $this->fosseMailer();

        $mailer->annulation($reponse);
        $this->assertSame('Annulation : Fosse du 05/12/2026', $this->envoyes[0]->sujet);
        $this->assertStringContainsString('est annulée', $this->envoyes[0]->html);

        $this->assertFalse($mailer->desistementDp($reponse), 'sans responsable, rien n\'est envoyé');
        $reponse->getInvitation()->setDp((new User())->setPrenom('Alex')->setNom('Dupont')->setEmail('alex@example.test'));
        $this->assertTrue($mailer->desistementDp($reponse));
        $this->assertSame('alex@example.test', $this->envoyes[1]->toEmail);
        $this->assertSame('Désistement : Fosse du 05/12/2026', $this->envoyes[1]->sujet);
        $this->assertStringContainsString('Camille Martin', $this->envoyes[1]->html);
    }

    public function testCommunicationAUnObjetPrefixeEtUnCorpsNettoye(): void
    {
        $communication = (new Communication())
            ->setTitre("Assemblée\ngénérale")
            ->setContenu('<h2>Convocation</h2><p>Le <em>12 janvier</em></p><script>x()</script>');
        $mailer = new CommunicationMailer($this->sender, $this->renderer, new ContenuMailSanitizer());

        $mailer->envoyer($communication, $this->membre());
        $mailer->envoyer($communication, $this->membre(), test: true);

        $this->assertSame('CN95 Plongée – Assemblée générale', $this->envoyes[0]->sujet);
        $this->assertSame('[Test] CN95 Plongée – Assemblée générale', $this->envoyes[1]->sujet);
        $html = $this->envoyes[0]->html;
        $this->assertStringContainsString('Bonjour à toutes et à tous', $html);
        $this->assertStringContainsString('<h2>Convocation</h2>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('indiquer votre choix', $html, 'aucun lien ajouté');
    }

    public function testInformationRgpdNeProposePlusDeChoixSurLesCommunications(): void
    {
        $liens = ['accuse' => 'https://exemple.test/rgpd/accuse/x', 'politique' => 'https://exemple.test/confidentialite'];
        $mailer = new RgpdMailer($this->sender, $this->renderer);

        $mailer->information($this->membre(), $liens);
        $mailer->information($this->membre(), $liens, representantLegal: true);

        foreach ($this->envoyes as $mail) {
            $this->assertStringContainsString('href="https://exemple.test/rgpd/accuse/x"', $mail->html);
            $this->assertStringNotContainsString("J'accepte", $mail->html);
            $this->assertStringNotContainsString('Communications facultatives', $mail->html);
            $this->assertStringContainsString('communications officielles du bureau', $mail->html);
        }
        $this->assertStringContainsString('Camille Martin', $this->envoyes[1]->html);
    }
}
