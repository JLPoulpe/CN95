<?php

namespace App\Controller\Admin;

use App\Entity\Role;
use App\Entity\User;
use App\Form\UserType;
use App\Mail\CompteMailer;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/admin/users')]
class UserController extends AbstractController
{
    #[Route('', name: 'admin_user_index')]
    public function index(Request $request, UserRepository $users): Response
    {
        $q = $request->query->getString('q');

        return $this->render('admin/user/index.html.twig', [
            'users' => $users->search($q),
            'q' => $q,
        ]);
    }

    #[Route('/new', name: 'admin_user_new')]
    public function new(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, RoleRepository $roles, CompteMailer $comptes): Response
    {
        $user = new User();
        if ($adherent = $roles->findOneBy(['code' => Role::ADHERENT])) {
            $user->addUserRole($adherent);
        }
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Mot de passe aléatoire inutilisable : le compte ne se connecte qu'après activation
            $user->setPassword($hasher->hashPassword($user, bin2hex(random_bytes(32))));
            $jeton = $user->genererActivation(new \DateTimeImmutable());
            $em->persist($user);
            $em->flush();
            if ($comptes->activation($user, $this->lienActivation($jeton))) {
                $this->addFlash('success', 'Utilisateur créé. Un lien d\'activation lui a été envoyé par mail.');
            } else {
                $this->addFlash('error', 'Utilisateur créé, mais l\'envoi du mail d\'activation a échoué. Réessayez depuis sa fiche.');
            }

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/new.html.twig', ['form' => $form]);
    }

    /** Renvoie un lien d'activation ; le précédent n'est plus valable. */
    #[Route('/{id}/activation', name: 'admin_user_activation', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function activation(User $user, Request $request, EntityManagerInterface $em, CompteMailer $comptes): Response
    {
        if (!$this->isCsrfTokenValid('user_activation_'.$user->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Session expirée, merci de réessayer.');
        } elseif (!$user->activationEnAttente()) {
            $this->addFlash('error', 'Ce compte est déjà activé.');
        } else {
            $jeton = $user->genererActivation(new \DateTimeImmutable());
            $em->flush();
            if ($comptes->activation($user, $this->lienActivation($jeton))) {
                $this->addFlash('success', 'Nouveau lien d\'activation envoyé.');
            } else {
                $this->addFlash('error', 'L\'envoi du nouveau lien d\'activation a échoué.');
            }
        }

        return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
    }

    private function lienActivation(string $jeton): string
    {
        return $this->generateUrl('compte_activation', ['token' => $jeton], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    #[Route('/{id}/edit', name: 'admin_user_edit', requirements: ['id' => '\\d+'])]
    public function edit(User $user, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Utilisateur modifié.');

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/edit.html.twig', ['form' => $form, 'user' => $user]);
    }
}
