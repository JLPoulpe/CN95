<?php

namespace App\Mail;

use App\Entity\MailConfiguration;
use App\Repository\MailConfigurationRepository;
use Doctrine\ORM\EntityManagerInterface;

class MailConfigurationManager
{
    public function __construct(
        private readonly MailConfigurationRepository $configurations,
        private readonly SecretBox $secretBox,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function courante(): ?MailConfiguration
    {
        return $this->configurations->findOneBy([]);
    }

    /**
     * Configuration du plugin déchiffrée, complétée par l'expéditeur.
     *
     * @return array<string, string>
     */
    public function configPlugin(MailConfiguration $configuration): array
    {
        $valeurs = json_decode($this->secretBox->dechiffrer($configuration->getConfigChiffree()), true, 512, JSON_THROW_ON_ERROR);

        return $valeurs + [
            'expediteur_nom' => $configuration->getExpediteurNom(),
            'expediteur_email' => $configuration->getExpediteurEmail(),
        ];
    }

    /** @param array<string, string> $valeurs Valeurs du plugin, sans l'expéditeur */
    public function enregistrer(string $pluginCode, string $expediteurNom, string $expediteurEmail, array $valeurs): void
    {
        $configuration = $this->courante() ?? new MailConfiguration();
        $configuration
            ->setPluginCode($pluginCode)
            ->setExpediteurNom($expediteurNom)
            ->setExpediteurEmail($expediteurEmail)
            ->setConfigChiffree($this->secretBox->chiffrer(json_encode($valeurs, JSON_THROW_ON_ERROR)));

        $this->em->persist($configuration);
        $this->em->flush();
    }
}
