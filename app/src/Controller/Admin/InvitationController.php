<?php

namespace App\Controller\Admin;

use App\Controller\PlanningController as PublicPlanningController;
use App\Entity\ActiviteExterne;
use App\Entity\InvitationFosse;
use App\Entity\ReponseFosse;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\StatutInvitationFosse;
use App\Enum\StatutReponseFosse;
use App\Form\InvitationFosseType;
use App\Mail\ContenuMailSanitizer;
use App\Mail\FosseMailer;
use App\Mail\MailMessage;
use App\Mail\MailSender;
use App\Repository\ActiviteExterneRepository;
use App\Repository\InvitationFosseRepository;
use App\Repository\UserRepository;
use App\Security\Voter\InvitationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/admin/invitations')]
class InvitationController extends AbstractController
{
    #[Route('', name: 'admin_invitation_index_defaut')]
    public function indexDefaut(ActiviteExterneRepository $activites): Response
    {
        $premiere = $activites->findOneBy([], ['libelle' => 'ASC']);
        if (null === $premiere) {
            $this->addFlash('error', 'Aucune activité externe n\'existe encore.');

            // La gestion des activités externes fait partie de la configuration des activités
            return $this->redirectToRoute($this->isGranted('PERM_CONFIG_ACTIVITES') ? 'admin_activite_externe_index' : 'app_member');
        }

        return $this->redirectToRoute('admin_invitation_index', ['activiteExterneId' => $premiere->getId()]);
    }

    #[Route('/activite/{activiteExterneId}', name: 'admin_invitation_index', requirements: ['activiteExterneId' => '\\d+'])]
    public function index(
        #[MapEntity(id: 'activiteExterneId')] ActiviteExterne $activiteExterne,
        InvitationFosseRepository $invitations,
        ActiviteExterneRepository $activites,
    ): Response {
        return $this->render('admin/invitation/index.html.twig', [
            'activiteExterne' => $activiteExterne,
            'activites' => $activites->findBy([], ['libelle' => 'ASC']),
            'invitations' => $invitations->findAffichables(PublicPlanningController::lundiDe(new \DateTimeImmutable('today')), $activiteExterne, $this->codesRolesVisibles()),
        ]);
    }

    #[Route('/activite/{activiteExterneId}/new', name: 'admin_invitation_new', requirements: ['activiteExterneId' => '\\d+'])]
    public function new(
        #[MapEntity(id: 'activiteExterneId')] ActiviteExterne $activiteExterne,
        Request $request,
        EntityManagerInterface $em,
        UserRepository $users,
        FosseMailer $mailer,
        ContenuMailSanitizer $sanitizer,
    ): Response {
        $utilisateur = $this->utilisateur();
        // Le commentaire est pré-rempli avec le texte par défaut de l'activité, modifiable avant l'envoi
        $invitation = (new InvitationFosse())->setActivite($activiteExterne)->setCommentaire($activiteExterne->getTexteParDefaut());
        // Par défaut, l'invitation est visible par les rôles de son créateur (hors adhérent et administrateur)
        foreach ($utilisateur->getUserRoles() as $role) {
            if (!in_array($role->getCode(), [Role::ADHERENT, Role::ADMIN], true)) {
                $invitation->addRole($role);
            }
        }
        $form = $this->createForm(InvitationFosseType::class, $invitation, ['activiteExterne' => $activiteExterne]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && !$invitation->estGerablePar($utilisateur)) {
            $form->addError(new FormError('Choisir au moins un de vos rôles : sinon vous ne verriez plus cette invitation.'));
        } elseif ($form->isSubmitted() && $form->isValid()) {
            $invitation->setCommentaire($sanitizer->commentaire($invitation->getCommentaire()));
            $em->persist($invitation);

            $reponses = [];
            foreach ($users->findByAptitudePrincipale($invitation->getAptitudes()->toArray()) as $user) {
                $reponse = new ReponseFosse($invitation, $user);
                $em->persist($reponse);
                $reponses[] = $reponse;
            }
            $em->flush();

            $echecs = 0;
            foreach ($reponses as $reponse) {
                $mailer->invitation($reponse) || ++$echecs;
            }

            $this->addFlash($echecs > 0 ? 'error' : 'success', sprintf(
                '%d mail(s) envoyé(s) pour l\'activité « %s » du %s.%s',
                count($reponses) - $echecs,
                $activiteExterne->getLibelle(),
                $invitation->getDate()->format('d/m/Y'),
                $echecs > 0 ? sprintf(' %d échec(s) d\'envoi.', $echecs) : '',
            ));

            return $this->redirectToRoute('admin_invitation_show', ['id' => $invitation->getId()]);
        }

        return $this->render('admin/invitation/new.html.twig', ['form' => $form, 'activiteExterne' => $activiteExterne]);
    }

    /** Envoi d'un mail d'essai avec les valeurs saisies dans le formulaire de création, sans rien enregistrer. */
    #[Route('/activite/{activiteExterneId}/test-mail', name: 'admin_invitation_test_mail', requirements: ['activiteExterneId' => '\\d+'], methods: ['POST'])]
    public function testMail(
        #[MapEntity(id: 'activiteExterneId')] ActiviteExterne $activiteExterne,
        Request $request,
        FosseMailer $mailer,
        MailSender $sender,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('invitation_test_mail', $request->request->getString('_token'))) {
            return new JsonResponse(['succes' => false, 'message' => 'Session expirée, rechargez la page.'], Response::HTTP_FORBIDDEN);
        }

        $saisie = $request->request->all('invitation_fosse');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($saisie['date'] ?? ''));
        $email = $request->request->getString('email');

        if (false === $date) {
            return new JsonResponse(['succes' => false, 'message' => 'Renseigner une date pour générer l\'aperçu.'], Response::HTTP_BAD_REQUEST);
        }
        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['succes' => false, 'message' => 'Adresse mail invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $libelle = $activiteExterne->getLibelle();
        $dateAffichee = $date->format('d/m/Y');
        $html = $mailer->htmlInvitation($libelle, $dateAffichee, (string) ($saisie['commentaire'] ?? ''));

        $envoye = $sender->envoyer(new MailMessage($email, null, '[Test] Invitation : '.$libelle.' du '.$dateAffichee, $html));

        return new JsonResponse(
            ['succes' => $envoye, 'message' => $envoye ? 'Mail de test mis en file d\'envoi.' : 'L\'envoi du mail de test a échoué.'],
            $envoye ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }

    #[Route('/{id}', name: 'admin_invitation_show', requirements: ['id' => '\\d+'])]
    public function show(InvitationFosse $invitation, ContenuMailSanitizer $sanitizer): Response
    {
        $this->denyAccessUnlessGranted(InvitationVoter::GERER, $invitation);

        $editForm = $this->createForm(InvitationFosseType::class, $invitation, [
            'editionLimitee' => true,
            'activiteExterne' => $invitation->getActivite(),
            'action' => $this->generateUrl('admin_invitation_edit', ['id' => $invitation->getId()]),
        ]);

        return $this->render('admin/invitation/show.html.twig', [
            'invitation' => $invitation,
            'editForm' => $editForm,
            'compte' => $this->compter($invitation),
            'commentaire' => $sanitizer->commentaire($invitation->getCommentaire()),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_invitation_edit', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function edit(InvitationFosse $invitation, Request $request, EntityManagerInterface $em, ContenuMailSanitizer $sanitizer): Response
    {
        $this->denyAccessUnlessGranted(InvitationVoter::GERER, $invitation);

        if (!$invitation->isEnvoyable()) {
            $this->addFlash('error', 'Cette invitation ne peut plus être modifiée.');

            return $this->redirectToRoute('admin_invitation_show', ['id' => $invitation->getId()]);
        }

        $form = $this->createForm(InvitationFosseType::class, $invitation, ['editionLimitee' => true, 'activiteExterne' => $invitation->getActivite()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $invitation->setCommentaire($sanitizer->commentaire($invitation->getCommentaire()));
            $em->flush();
            $this->addFlash('success', 'Invitation modifiée.');
        } else {
            $this->addFlash('error', 'Modification invalide.');
        }

        return $this->redirectToRoute('admin_invitation_show', ['id' => $invitation->getId()]);
    }

    #[Route('/{id}/supprimer', name: 'admin_invitation_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(InvitationFosse $invitation, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(InvitationVoter::GERER, $invitation);

        $activiteExterneId = $invitation->getActivite()->getId();

        if ($this->isCsrfTokenValid('invitation_delete_'.$invitation->getId(), $request->request->getString('_token'))) {
            $em->remove($invitation);
            $em->flush();
            $this->addFlash('success', 'Invitation supprimée.');
        }

        return $this->redirectToRoute('admin_invitation_index', ['activiteExterneId' => $activiteExterneId]);
    }

    #[Route('/{id}/inscrits', name: 'admin_invitation_inscrits', requirements: ['id' => '\\d+'])]
    public function inscrits(InvitationFosse $invitation): Response
    {
        $this->denyAccessUnlessGranted(InvitationVoter::GERER, $invitation);

        $parStatut = fn (StatutReponseFosse $statut) => array_values(array_filter($invitation->getReponses()->toArray(), fn (ReponseFosse $r) => $statut === $r->getStatut()));

        // Liste principale par ordre alphabétique ; liste d'attente par ordre d'arrivée (c'est l'ordre de promotion)
        $principale = $parStatut(StatutReponseFosse::Inscrit);
        usort($principale, fn (ReponseFosse $a, ReponseFosse $b) => [$a->getUser()->getNom(), $a->getUser()->getPrenom()] <=> [$b->getUser()->getNom(), $b->getUser()->getPrenom()]);
        $attente = $parStatut(StatutReponseFosse::ListeAttente);
        usort($attente, fn (ReponseFosse $a, ReponseFosse $b) => [$a->getRepondueAt(), $a->getId()] <=> [$b->getRepondueAt(), $b->getId()]);

        $moniteur = fn (ReponseFosse $r) => $r->getUser()->hasRole(Role::MONITEUR);

        return $this->render('admin/invitation/inscrits.html.twig', [
            'invitation' => $invitation,
            'moniteurs_principale' => array_values(array_filter($principale, $moniteur)),
            'moniteurs_attente' => array_values(array_filter($attente, $moniteur)),
            'plongeurs_principale' => array_values(array_filter($principale, fn (ReponseFosse $r) => !$moniteur($r))),
            'plongeurs_attente' => array_values(array_filter($attente, fn (ReponseFosse $r) => !$moniteur($r))),
            'total_principale' => count($principale),
            'total_attente' => count($attente),
        ]);
    }

    private const JOURS = ['LUNDI', 'MARDI', 'MERCREDI', 'JEUDI', 'VENDREDI', 'SAMEDI', 'DIMANCHE'];
    private const MOIS = ['JANVIER', 'FÉVRIER', 'MARS', 'AVRIL', 'MAI', 'JUIN', 'JUILLET', 'AOÛT', 'SEPTEMBRE', 'OCTOBRE', 'NOVEMBRE', 'DÉCEMBRE'];
    /** Nombre de lignes réservées aux moniteurs / encadrants sur la feuille (au minimum). */
    private const PLACES_MONITEURS = 8;
    /** Croix épaisse (gras, noire) : un CSV ne porte aucune mise en forme, le caractère la remplace. */
    private const CROIX = '✖';

    /** Feuille d'émargement de la fosse : moniteurs, plongeurs puis liste d'attente, avec des lignes vides jusqu'à la limite. */
    #[Route('/{id}/export-inscrits', name: 'admin_invitation_export_inscrits', requirements: ['id' => '\\d+'])]
    public function exportInscrits(InvitationFosse $invitation): Response
    {
        $this->denyAccessUnlessGranted(InvitationVoter::GERER, $invitation);

        $parStatut = fn (StatutReponseFosse $statut) => array_values(array_filter($invitation->getReponses()->toArray(), fn (ReponseFosse $r) => $statut === $r->getStatut()));
        $parNom = fn (ReponseFosse $a, ReponseFosse $b) => [mb_strtoupper((string) $a->getUser()->getNom()), $a->getUser()->getPrenom()] <=> [mb_strtoupper((string) $b->getUser()->getNom()), $b->getUser()->getPrenom()];

        $moniteurs = array_values(array_filter($parStatut(StatutReponseFosse::Inscrit), fn (ReponseFosse $r) => $r->getUser()->hasRole(Role::MONITEUR)));
        $plongeurs = array_values(array_filter($parStatut(StatutReponseFosse::Inscrit), fn (ReponseFosse $r) => !$r->getUser()->hasRole(Role::MONITEUR)));
        usort($moniteurs, $parNom);
        usort($plongeurs, $parNom);
        // La liste d'attente reste dans l'ordre d'arrivée
        $attente = $parStatut(StatutReponseFosse::ListeAttente);
        usort($attente, fn (ReponseFosse $a, ReponseFosse $b) => [$a->getRepondueAt(), $a->getId()] <=> [$b->getRepondueAt(), $b->getId()]);

        $fosse = $invitation->getDate();
        $ligne = fn (?ReponseFosse $reponse, int|string $place) => null === $reponse
            ? [$place, '', '', '', '', '', '']
            : [
                $place,
                mb_strtoupper(trim((string) $reponse->getUser()->getNom())),
                trim((string) $reponse->getUser()->getPrenom()),
                (string) $reponse->getUser()->getAptitude(),
                (string) $reponse->getUser()->getAptitudePreparee(),
                $reponse->getUser()->estMineur($fosse) ? self::CROIX : '',
                (string) $reponse->getUser()->getTelephone(),
            ];
        $vide = ['', '', '', '', '', '', ''];

        $lignes = [
            [sprintf('%s DU %s %d %s %d', mb_strtoupper($invitation->getActivite()->getLibelle()), self::JOURS[(int) $fosse->format('N') - 1], $fosse->format('j'), self::MOIS[(int) $fosse->format('n') - 1], $fosse->format('Y')), '', '', '', '', '', ''],
            ['Places', 'Nom', 'Prénom', 'Aptitudes', 'Aptitudes préparées', 'Mineur', 'Téléphone'],
            ['MONITEURS / ENCADRANTS', '', '', '', '', '', ''],
        ];

        // Places libres jusqu'à la limite : les premières sont réservées aux moniteurs (jusqu'à PLACES_MONITEURS lignes), le reste aux plongeurs
        $libres = max(0, ($invitation->getLimiteInscrits() ?? 0) - count($moniteurs) - count($plongeurs));
        $libresMoniteurs = min($libres, max(0, self::PLACES_MONITEURS - count($moniteurs)));

        $place = 0;
        for ($i = 0, $n = count($moniteurs) + $libresMoniteurs; $i < $n; ++$i) {
            $lignes[] = $ligne($moniteurs[$i] ?? null, ++$place);
        }

        $lignes[] = ['PLONGEURS', '', '', '', '', '', ''];
        for ($i = 0, $n = count($plongeurs) + $libres - $libresMoniteurs; $i < $n; ++$i) {
            $lignes[] = $ligne($plongeurs[$i] ?? null, ++$place);
        }

        $lignes[] = ['PLONGEURS EN LISTE D\'ATTENTE', '', '', '', '', '', ''];
        $nbAttente = max(count($attente), $invitation->getLimiteAttente() ?? 0);
        for ($i = 0; $i < $nbAttente; ++$i) {
            $lignes[] = isset($attente[$i]) ? $ligne($attente[$i], '') : $vide;
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF"); // BOM : accents corrects à l'ouverture dans Excel
        foreach ($lignes as $champs) {
            fputcsv($handle, $champs, ',', '"', '', "\r\n");
        }
        rewind($handle);
        $contenu = stream_get_contents($handle);
        fclose($handle);

        $response = new Response($contenu);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            sprintf('%s_%s.csv', (new AsciiSlugger())->slug((string) $invitation->getActivite()->getLibelle(), '_'), $invitation->getDate()->format('Y-m-d')),
        ));

        return $response;
    }

    #[Route('/{id}/relancer', name: 'admin_invitation_relancer', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function relancer(InvitationFosse $invitation, Request $request, FosseMailer $mailer): Response
    {
        $this->denyAccessUnlessGranted(InvitationVoter::GERER, $invitation);

        if ($this->isCsrfTokenValid('invitation_relancer_'.$invitation->getId(), $request->request->getString('_token'))) {
            if (!$invitation->isEnvoyable()) {
                $this->addFlash('error', 'Cette invitation ne peut plus recevoir d\'envois.');
            } else {
                $relances = 0;
                $echecs = 0;
                foreach ($invitation->getReponses() as $reponse) {
                    if (StatutReponseFosse::EnAttente === $reponse->getStatut()) {
                        $mailer->invitation($reponse, relance: true) ? ++$relances : ++$echecs;
                    }
                }
                $this->addFlash($echecs > 0 ? 'error' : 'success', sprintf('%d relance(s) envoyée(s).', $relances).($echecs > 0 ? sprintf(' %d échec(s) d\'envoi.', $echecs) : ''));
            }
        }

        return $this->redirectToRoute('admin_invitation_show', ['id' => $invitation->getId()]);
    }

    #[Route('/{id}/annuler', name: 'admin_invitation_annuler', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function annuler(InvitationFosse $invitation, Request $request, EntityManagerInterface $em, FosseMailer $mailer): Response
    {
        $this->denyAccessUnlessGranted(InvitationVoter::GERER, $invitation);

        if ($this->isCsrfTokenValid('invitation_annuler_'.$invitation->getId(), $request->request->getString('_token'))) {
            if (!$invitation->isEnvoyable()) {
                $this->addFlash('error', 'Cette invitation ne peut plus être annulée.');
            } else {
                $invitation->setStatut(StatutInvitationFosse::Annulee);
                $em->flush();

                $annulations = 0;
                $echecs = 0;
                foreach ($invitation->getReponses() as $reponse) {
                    if ($reponse->aRepondu()) {
                        $mailer->annulation($reponse) ? ++$annulations : ++$echecs;
                    }
                }
                $this->addFlash($echecs > 0 ? 'error' : 'success', sprintf('Invitation annulée. %d mail(s) d\'annulation envoyé(s).', $annulations).($echecs > 0 ? sprintf(' %d échec(s) d\'envoi.', $echecs) : ''));
            }
        }

        return $this->redirectToRoute('admin_invitation_show', ['id' => $invitation->getId()]);
    }

    private function utilisateur(): User
    {
        $utilisateur = $this->getUser();
        \assert($utilisateur instanceof User);

        return $utilisateur;
    }

    /** @return list<string>|null Codes des rôles qui limitent les invitations visibles ; null pour un administrateur (toutes) */
    private function codesRolesVisibles(): ?array
    {
        $utilisateur = $this->utilisateur();
        if ($utilisateur->hasRole(Role::ADMIN)) {
            return null;
        }

        return array_values(array_map(fn (Role $role) => $role->getCode(), $utilisateur->getUserRoles()->toArray()));
    }

    /** @return array{sans_reponse: int, inscrits: int, liste_attente: int, refus: int, desistements: int} */
    private function compter(InvitationFosse $invitation): array
    {
        $compte = ['sans_reponse' => 0, 'inscrits' => 0, 'liste_attente' => 0, 'refus' => 0, 'desistements' => 0];
        foreach ($invitation->getReponses() as $reponse) {
            match ($reponse->getStatut()) {
                StatutReponseFosse::EnAttente => ++$compte['sans_reponse'],
                StatutReponseFosse::Inscrit => ++$compte['inscrits'],
                StatutReponseFosse::ListeAttente => ++$compte['liste_attente'],
                StatutReponseFosse::Refus => ++$compte['refus'],
                StatutReponseFosse::Desistement => ++$compte['desistements'],
            };
        }

        return $compte;
    }
}
