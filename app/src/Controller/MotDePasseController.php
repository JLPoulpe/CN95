<?php

namespace App\Controller;

use App\Entity\User;
use App\Mail\CompteMailer;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** « Mot de passe oublié » : demande par identifiant ou e-mail, puis lien à usage unique valable 1 heure. */
class MotDePasseController extends AbstractController
{
    public const LONGUEUR_MINIMALE = 12;

    #[Route('/mot-de-passe-oublie', name: 'app_mot_de_passe_oublie', methods: ['GET', 'POST'])]
    public function demande(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        CompteMailer $comptes,
        #[Autowire(service: 'limiter.mot_de_passe_oublie_ip')] RateLimiterFactory $limiteIp,
        #[Autowire(service: 'limiter.mot_de_passe_oublie_compte')] RateLimiterFactory $limiteCompte,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_profil_mot_de_passe');
        }

        $form = $this->createFormBuilder()
            ->add('identifiant', TextType::class, [
                'label' => 'Identifiant ou adresse e-mail',
                'constraints' => [new NotBlank(), new Length(max: 180)],
            ])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $identifiant = mb_strtolower(trim((string) $form->get('identifiant')->getData()));

            // Réponse identique que le compte existe ou non, ou que la limite soit atteinte : aucune énumération possible
            $autorise = $limiteIp->create($request->getClientIp() ?? 'inconnue')->consume()->isAccepted()
                && $limiteCompte->create(hash('sha256', $identifiant))->consume()->isAccepted();

            $user = $autorise ? $users->loadUserByIdentifier($identifiant) : null;
            // Un compte local (cn95.local) n'a pas de vraie boîte mail ; un compte en attente d'activation suit son propre lien
            if ($user instanceof User && !$user->estCompteLocal() && !$user->activationEnAttente()) {
                $jeton = $user->genererReinitialisation(new \DateTimeImmutable());
                $em->flush();
                $comptes->reinitialisation($user, $this->generateUrl('app_mot_de_passe_reinitialiser', ['token' => $jeton], UrlGeneratorInterface::ABSOLUTE_URL));
            }

            $this->addFlash('success', 'Si un compte correspond, un mail contenant un lien de réinitialisation vient d\'être envoyé (valable 1 heure).');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/mot_de_passe_oublie.html.twig', ['form' => $form], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/mot-de-passe/reinitialiser/{token}', name: 'app_mot_de_passe_reinitialiser', requirements: ['token' => '[0-9a-f]{64}'], methods: ['GET', 'POST'])]
    public function reinitialiser(string $token, Request $request, UserRepository $users, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $user = $users->findOneBy(['resetTokenHash' => hash('sha256', $token)]);
        if (null === $user || !$user->reinitialisationValide($token, new \DateTimeImmutable())) {
            return $this->render('compte/reinitialisation.html.twig', ['valide' => false], new Response(status: Response::HTTP_NOT_FOUND));
        }

        $form = $this->formulaireNouveauMotDePasse();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->terminerReinitialisation($hasher->hashPassword($user, $form->get('motDePasse')->getData()));
            $em->flush();
            $this->addFlash('success', 'Votre mot de passe a été modifié. Connectez-vous avec le nouveau.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('compte/reinitialisation.html.twig', ['valide' => true, 'form' => $form], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function formulaireNouveauMotDePasse(): \Symfony\Component\Form\FormInterface
    {
        return $this->createFormBuilder()
            ->add('motDePasse', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => sprintf('Nouveau mot de passe (%d caractères minimum)', self::LONGUEUR_MINIMALE)],
                'second_options' => ['label' => 'Confirmer le mot de passe'],
                'invalid_message' => 'Les deux mots de passe doivent être identiques.',
                'constraints' => [new NotBlank(), new Length(min: self::LONGUEUR_MINIMALE, max: 4096)],
            ])
            ->getForm();
    }
}
