<?php

namespace App\Controller\Admin;

use App\Entity\MailTemplate;
use App\Form\MailTemplateType;
use App\Mail\MailTemplateRenderer;
use App\Mail\ContenuMailSanitizer;
use App\Repository\InvitationFosseRepository;
use App\Repository\MailTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/mail-templates')]
class MailTemplateController extends AbstractController
{
    #[Route('', name: 'admin_mail_template_index')]
    public function index(MailTemplateRepository $templates): Response
    {
        return $this->render('admin/mail_template/index.html.twig', [
            'templates' => $templates->findBy([], ['type' => 'ASC', 'nom' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'admin_mail_template_new')]
    public function new(Request $request, EntityManagerInterface $em, MailTemplateRepository $templates, ContenuMailSanitizer $sanitizer): Response
    {
        $template = new MailTemplate();
        $form = $this->createForm(MailTemplateType::class, $template);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $template->setContenu($sanitizer->nettoyer($template->getContenu()));
            if ($template->isParDefaut()) {
                $templates->definirParDefaut($template);
            }
            $em->persist($template);
            $em->flush();
            $this->addFlash('success', 'Modèle de mail créé.');

            return $this->redirectToRoute('admin_mail_template_index');
        }

        return $this->render('admin/mail_template/form.html.twig', ['form' => $form, 'template' => $template]);
    }

    #[Route('/{id}/edit', name: 'admin_mail_template_edit', requirements: ['id' => '\\d+'])]
    public function edit(#[MapEntity] MailTemplate $template, Request $request, EntityManagerInterface $em, MailTemplateRepository $templates, ContenuMailSanitizer $sanitizer): Response
    {
        $etaitParDefaut = $template->isParDefaut();
        $typeInitial = $template->getType();
        $form = $this->createForm(MailTemplateType::class, $template);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Il doit toujours exister un modèle par défaut pour chaque type : on ne peut pas le retirer sans en choisir un autre
            if ($etaitParDefaut && ($typeInitial !== $template->getType() || !$template->isParDefaut())) {
                $form->addError(new FormError('Ce modèle est le modèle par défaut de son type : choisissez d\'abord un autre modèle par défaut.'));
            } else {
                $template->setContenu($sanitizer->nettoyer($template->getContenu()));
                if ($template->isParDefaut()) {
                    $templates->definirParDefaut($template);
                }
                $em->flush();
                $this->addFlash('success', 'Modèle de mail enregistré.');

                return $this->redirectToRoute('admin_mail_template_index');
            }
        }

        return $this->render('admin/mail_template/form.html.twig', ['form' => $form, 'template' => $template]);
    }

    #[Route('/{id}/par-defaut', name: 'admin_mail_template_par_defaut', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function parDefaut(#[MapEntity] MailTemplate $template, Request $request, EntityManagerInterface $em, MailTemplateRepository $templates): Response
    {
        if ($this->isCsrfTokenValid('mail_template_'.$template->getId(), $request->request->getString('_token'))) {
            $templates->definirParDefaut($template);
            $em->flush();
            $this->addFlash('success', sprintf('« %s » est désormais le modèle par défaut.', $template->getNom()));
        }

        return $this->redirectToRoute('admin_mail_template_index');
    }

    #[Route('/{id}/supprimer', name: 'admin_mail_template_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(#[MapEntity] MailTemplate $template, Request $request, EntityManagerInterface $em, InvitationFosseRepository $invitations): Response
    {
        if (!$this->isCsrfTokenValid('mail_template_'.$template->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Session expirée, merci de réessayer.');
        } elseif ($template->isParDefaut()) {
            $this->addFlash('error', 'Ce modèle est le modèle par défaut de son type : choisissez d\'abord un autre modèle par défaut.');
        } elseif (($nombre = $invitations->count(['mailTemplate' => $template])) > 0) {
            $this->addFlash('error', sprintf('Ce modèle est utilisé par %d invitation(s) et ne peut pas être supprimé.', $nombre));
        } else {
            $em->remove($template);
            $em->flush();
            $this->addFlash('success', 'Modèle de mail supprimé.');
        }

        return $this->redirectToRoute('admin_mail_template_index');
    }

    /** Aperçu avec des valeurs d'exemple, tel qu'il sera reçu. */
    #[Route('/{id}/apercu', name: 'admin_mail_template_apercu', requirements: ['id' => '\\d+'])]
    public function apercu(#[MapEntity] MailTemplate $template, MailTemplateRenderer $renderer): Response
    {
        $html = $renderer->rendre(
            $template,
            ['date' => '19/10/2026', 'activite' => 'Fosse', 'prenom' => 'Prénom', 'nom' => 'Nom', 'membre' => 'Prénom Nom'],
            'Exemple de commentaire de l\'invitation.',
            ['intro' => 'Exemple de liens (ajoutés automatiquement) :', 'items' => ['Lien de réponse' => '#']],
        );

        return new Response($html);
    }
}
