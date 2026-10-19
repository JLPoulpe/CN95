<?php

namespace App\Controller\Admin;

use App\Entity\Communication;
use App\Entity\User;
use App\Form\CommunicationType;
use App\Form\DestinatairesCommunicationType;
use App\Mail\CommunicationMailer;
use App\Mail\ContenuMailSanitizer;
use App\Repository\CommunicationRepository;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Communications officielles par mail. L'accès est réglé par PERM_COMMUNICATION (voir security.yaml). */
#[Route('/admin/communications')]
class CommunicationController extends AbstractController
{
    #[Route('', name: 'admin_communication_index')]
    public function index(CommunicationRepository $communications): Response
    {
        return $this->render('admin/communication/index.html.twig', ['communications' => $communications->findRecentes()]);
    }

    #[Route('/new', name: 'admin_communication_new')]
    public function new(Request $request, EntityManagerInterface $em, ContenuMailSanitizer $sanitizer): Response
    {
        $communication = (new Communication())->setAuteur($this->utilisateur());
        $form = $this->createForm(CommunicationType::class, $communication);
        $form->handleRequest($request);

        if ($this->contenuValide($form, $communication, $sanitizer)) {
            $em->persist($communication);
            $em->flush();
            $this->addFlash('success', 'Communication enregistrée. Vérifiez l\'aperçu, puis choisissez les destinataires pour l\'envoyer.');

            return $this->redirectToRoute('admin_communication_show', ['id' => $communication->getId()]);
        }

        return $this->render('admin/communication/form.html.twig', ['form' => $form, 'communication' => null]);
    }

    #[Route('/{id}', name: 'admin_communication_show', requirements: ['id' => '\\d+'])]
    public function show(Communication $communication): Response
    {
        return $this->render('admin/communication/show.html.twig', [
            'communication' => $communication,
            'destinataires' => $this->createForm(DestinatairesCommunicationType::class, null, [
                'action' => $this->generateUrl('admin_communication_envoyer', ['id' => $communication->getId()]),
            ]),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_communication_edit', requirements: ['id' => '\\d+'])]
    public function edit(Communication $communication, Request $request, EntityManagerInterface $em, ContenuMailSanitizer $sanitizer): Response
    {
        if ($communication->estEnvoyee()) {
            $this->addFlash('error', 'Une communication déjà envoyée ne peut plus être modifiée.');

            return $this->redirectToRoute('admin_communication_show', ['id' => $communication->getId()]);
        }

        $form = $this->createForm(CommunicationType::class, $communication);
        $form->handleRequest($request);

        if ($this->contenuValide($form, $communication, $sanitizer)) {
            $em->flush();
            $this->addFlash('success', 'Communication modifiée.');

            return $this->redirectToRoute('admin_communication_show', ['id' => $communication->getId()]);
        }

        return $this->render('admin/communication/form.html.twig', ['form' => $form, 'communication' => $communication]);
    }

    /** Le mail tel que le recevront les destinataires, affiché dans un cadre de la page de la communication. */
    #[Route('/{id}/apercu', name: 'admin_communication_apercu', requirements: ['id' => '\\d+'])]
    public function apercu(Communication $communication, CommunicationMailer $mailer): Response
    {
        return new Response($mailer->html($communication));
    }

    /** Envoie la communication à l'adresse de l'utilisateur connecté seulement. */
    #[Route('/{id}/test', name: 'admin_communication_test', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function test(Communication $communication, Request $request, CommunicationMailer $mailer): Response
    {
        if (!$this->isCsrfTokenValid('communication_'.$communication->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Session expirée, merci de réessayer.');
        } elseif ($mailer->envoyer($communication, $this->utilisateur(), true)) {
            $this->addFlash('success', 'Mail de test mis en file d\'envoi à votre adresse.');
        } else {
            $this->addFlash('error', 'L\'envoi du mail de test a échoué.');
        }

        return $this->redirectToRoute('admin_communication_show', ['id' => $communication->getId()]);
    }

    #[Route('/{id}/envoyer', name: 'admin_communication_envoyer', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function envoyer(Communication $communication, Request $request, EntityManagerInterface $em, UserRepository $users, CommunicationMailer $mailer): Response
    {
        $form = $this->createForm(DestinatairesCommunicationType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Session expirée ou choix invalide, merci de réessayer.');
        } elseif ($communication->estEnvoyee()) {
            $this->addFlash('error', 'Cette communication a déjà été envoyée.');
        } else {
            $destinataires = $users->findPourCommunication($this->enTableau($form->get('roles')->getData()), $this->enTableau($form->get('aptitudes')->getData()));

            if ([] === $destinataires) {
                $this->addFlash('error', 'Aucun membre actif ne correspond à cette sélection.');
            } else {
                $echecs = 0;
                foreach ($destinataires as $destinataire) {
                    $mailer->envoyer($communication, $destinataire) || ++$echecs;
                }

                $communication->marquerEnvoyee(new \DateTimeImmutable(), count($destinataires) - $echecs);
                $em->flush();

                $this->addFlash($echecs > 0 ? 'error' : 'success', sprintf(
                    '%d mail(s) mis en file d\'envoi.%s',
                    count($destinataires) - $echecs,
                    $echecs > 0 ? sprintf(' %d échec(s) de mise en file.', $echecs) : '',
                ));
            }
        }

        return $this->redirectToRoute('admin_communication_show', ['id' => $communication->getId()]);
    }

    #[Route('/{id}/supprimer', name: 'admin_communication_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Communication $communication, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('communication_'.$communication->getId(), $request->request->getString('_token'))) {
            if ($communication->estEnvoyee()) {
                $this->addFlash('error', 'Une communication envoyée est conservée comme historique.');
            } else {
                $em->remove($communication);
                $em->flush();
                $this->addFlash('success', 'Brouillon supprimé.');
            }
        }

        return $this->redirectToRoute('admin_communication_index');
    }

    /** Valide le formulaire, nettoie le HTML saisi et refuse un contenu vide (un éditeur vide renvoie « <p></p> »). */
    private function contenuValide(FormInterface $form, Communication $communication, ContenuMailSanitizer $sanitizer): bool
    {
        if (!$form->isSubmitted() || !$form->isValid()) {
            return false;
        }

        $communication->setContenu($sanitizer->commentaire($communication->getContenu()));
        if ('' === trim(strip_tags($communication->getContenu()))) {
            $form->get('contenu')->addError(new FormError('Saisir le contenu de la communication.'));

            return false;
        }

        return true;
    }

    /** @return array<int, mixed> */
    private function enTableau(mixed $choix): array
    {
        return $choix instanceof Collection ? $choix->toArray() : (array) $choix;
    }

    private function utilisateur(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
