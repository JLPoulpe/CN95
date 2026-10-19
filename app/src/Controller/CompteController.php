<?php

namespace App\Controller;

use App\Entity\DemandeRgpd;
use App\Enum\TypeDemandeRgpd;
use App\Mail\RgpdMailer;
use App\Repository\DemandeRgpdRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Route('/compte')]
class CompteController extends AbstractController
{
    #[Route('/activation/{token}', name: 'compte_activation', requirements: ['token' => '[0-9a-f]{64}'], methods: ['GET', 'POST'])]
    public function activation(string $token, Request $request, UserRepository $users, DemandeRgpdRepository $demandes, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        // Le jeton n'est stocké que sous forme de hash : on le recalcule pour la recherche, puis on vérifie la validité
        $user = $users->findOneBy(['activationTokenHash' => hash('sha256', $token)]);
        if (null === $user || !$user->activationValide($token, new \DateTimeImmutable())) {
            return $this->render('compte/activation.html.twig', ['valide' => false], new Response(status: Response::HTTP_NOT_FOUND));
        }

        $form = $this->createFormBuilder()
            ->add('motDePasse', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Mot de passe (8 caractères minimum)'],
                'second_options' => ['label' => 'Confirmer le mot de passe'],
                'invalid_message' => 'Les deux mots de passe doivent être identiques.',
                'constraints' => [new NotBlank(), new Length(min: 8)],
            ])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->terminerActivation($hasher->hashPassword($user, $form->get('motDePasse')->getData()));

            // Valider le mot de passe vaut prise de connaissance de l'information RGPD affichée sur cette page.
            // Un simple clic sur le lien du mail ne suffit pas : un antivirus ou un aperçu peut l'ouvrir à la place du membre.
            if (!$demandes->existePourVersion($user, TypeDemandeRgpd::Information, RgpdMailer::VERSION_TEXTE)) {
                $accuse = new DemandeRgpd($user, TypeDemandeRgpd::Information, RgpdMailer::VERSION_TEXTE);
                $accuse->repondre(null);
                $em->persist($accuse);
            }
            $em->flush();
            $this->addFlash('success', 'Votre compte est activé. Vous pouvez maintenant vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('compte/activation.html.twig', [
            'valide' => true,
            'form' => $form,
            'user' => $user,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
