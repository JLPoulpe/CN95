<?php

namespace App\Controller\Admin;

use App\Mail\MailConfigurationManager;
use App\Mail\MailException;
use App\Mail\Plugin\MailPluginRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/mail')]
class MailConfigController extends AbstractController
{
    #[Route('', name: 'admin_mail_config', methods: ['GET', 'POST'])]
    public function edit(Request $request, MailPluginRegistry $plugins, MailConfigurationManager $configurations): Response
    {
        $courante = $configurations->courante();
        $valeurs = $courante ? $configurations->configPlugin($courante) : [];

        $codeSelectionne = $courante?->getPluginCode() ?? array_key_first($plugins->all());
        $expediteurNom = $courante?->getExpediteurNom() ?? '';
        $expediteurEmail = $courante?->getExpediteurEmail() ?? '';
        $resultatTest = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('mail_config', $request->request->getString('_token'))) {
                $this->addFlash('error', 'Session expirée, merci de réessayer.');

                return $this->redirectToRoute('admin_mail_config');
            }

            $codeSelectionne = $request->request->getString('plugin');
            $expediteurNom = trim($request->request->getString('expediteur_nom'));
            $expediteurEmail = trim($request->request->getString('expediteur_email'));
            $saisie = $request->request->all('config');
            $valeursPlugin = [];

            try {
                // Un secret laissé vide conserve la valeur déjà enregistrée pour le même plugin
                foreach ($plugins->get($codeSelectionne)->configFields() as $champ => $definition) {
                    $valeur = trim((string) ($saisie[$champ] ?? ''));
                    if ('' === $valeur && ($definition['secret'] ?? false) && $courante?->getPluginCode() === $codeSelectionne) {
                        $valeur = (string) ($valeurs[$champ] ?? '');
                    }
                    $valeursPlugin[$champ] = $valeur;
                }

                if ($request->request->has('tester')) {
                    $plugins->get($codeSelectionne)->testConnection($valeursPlugin + ['expediteur_nom' => $expediteurNom, 'expediteur_email' => $expediteurEmail]);
                    $resultatTest = ['succes' => true, 'message' => 'Connexion au service réussie.'];
                } else {
                    $configurations->enregistrer($codeSelectionne, $expediteurNom, $expediteurEmail, $valeursPlugin);
                    $this->addFlash('success', 'Configuration des mails enregistrée.');

                    return $this->redirectToRoute('admin_mail_config');
                }
            } catch (MailException|\InvalidArgumentException $e) {
                // Le résultat du test s'affiche dans la modal, l'enregistrement dans un flash
                if ($request->request->has('tester')) {
                    $resultatTest = ['succes' => false, 'message' => $e->getMessage()];
                } else {
                    $this->addFlash('error', $e->getMessage());
                }
            }

            $valeurs = $valeursPlugin;
        }

        return $this->render('admin/mail/index.html.twig', [
            'plugins' => $plugins->all(),
            'codeSelectionne' => $codeSelectionne,
            'champs' => $plugins->all()[$codeSelectionne]?->configFields() ?? [],
            'valeurs' => $valeurs,
            'expediteurNom' => $expediteurNom,
            'expediteurEmail' => $expediteurEmail,
            'configure' => null !== $courante,
            'resultatTest' => $resultatTest,
        ]);
    }
}
