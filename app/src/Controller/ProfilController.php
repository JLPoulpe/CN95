<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfilType;
use App\Rgpd\PurgeAnnuelle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** Espace « Mes données personnelles » : accessible à tout membre connecté, pour sa propre fiche uniquement. */
#[Route('/membre/profil')]
class ProfilController extends AbstractController
{
    #[Route('', name: 'app_profil', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        $form = $this->createForm(ProfilType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Vos données personnelles ont été enregistrées.');

            return $this->redirectToRoute('app_member');
        }

        return $this->render('profil/index.html.twig', [
            'form' => $form,
            // Arrivée juste après l'activation du compte : on demande de vérifier les informations et le niveau
            'bienvenue' => $request->query->getBoolean('bienvenue'),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** Changement du mot de passe par le membre connecté : l'ancien mot de passe est exigé. */
    #[Route('/mot-de-passe', name: 'app_profil_mot_de_passe', methods: ['GET', 'POST'])]
    public function motDePasse(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, Security $security): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        $form = $this->createFormBuilder()
            ->add('actuel', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false,
                'constraints' => [new NotBlank(), new UserPassword(message: 'Le mot de passe actuel est incorrect.')],
                'attr' => ['autocomplete' => 'current-password'],
            ])
            ->add('motDePasse', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => sprintf('Nouveau mot de passe (%d caractères minimum)', MotDePasseController::LONGUEUR_MINIMALE), 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => 'Confirmer le nouveau mot de passe', 'attr' => ['autocomplete' => 'new-password']],
                'invalid_message' => 'Les deux mots de passe doivent être identiques.',
                'constraints' => [new NotBlank(), new Length(min: MotDePasseController::LONGUEUR_MINIMALE, max: 4096)],
            ])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($hasher->hashPassword($user, $form->get('motDePasse')->getData()));
            $em->flush();
            // Le hash du mot de passe fait partie de l'identité de session : on rouvre la session pour ne pas déconnecter l'auteur du changement
            // (les sessions ouvertes ailleurs, elles, deviennent invalides)
            $security->login($user, 'form_login', 'main');
            $this->addFlash('success', 'Votre mot de passe a été modifié.');

            return $this->redirectToRoute('app_member');
        }

        return $this->render('profil/mot_de_passe.html.twig', ['form' => $form], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/supprimer', name: 'app_profil_supprimer', methods: ['POST'])]
    public function supprimer(Request $request, PurgeAnnuelle $purge, Security $security): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        if (!$this->isCsrfTokenValid('profil_supprimer', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Session expirée, merci de réessayer.');

            return $this->redirectToRoute('app_profil');
        }

        try {
            $purge->supprimerUtilisateur((int) $user->getId());
        } catch (\DomainException) {
            $this->addFlash('error', 'Vous êtes le dernier administrateur : désignez-en un autre avant de supprimer votre compte.');

            return $this->redirectToRoute('app_profil');
        }

        // Le compte n'existe plus : on ferme la session (le flash est ajouté ensuite, dans la nouvelle session)
        $security->logout(false);
        $this->addFlash('success', 'Vos données personnelles ont été supprimées. Vous ne recevrez plus d\'invitation et n\'avez plus accès à l\'outil.');

        return $this->redirectToRoute('app_login');
    }
}
