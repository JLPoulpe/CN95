<?php

namespace App\Entity;

use App\Repository\MailConfigurationRepository;
use Doctrine\ORM\Mapping as ORM;

/** Configuration unique du système d'envoi de mails. La configuration du plugin est chiffrée. */
#[ORM\Entity(repositoryClass: MailConfigurationRepository::class)]
#[ORM\Table(name: 'mail_configuration')]
class MailConfiguration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $pluginCode = '';

    #[ORM\Column(length: 100)]
    private string $expediteurNom = '';

    #[ORM\Column(length: 180)]
    private string $expediteurEmail = '';

    #[ORM\Column(type: 'text')]
    private string $configChiffree = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPluginCode(): string
    {
        return $this->pluginCode;
    }

    public function setPluginCode(string $pluginCode): static
    {
        $this->pluginCode = $pluginCode;

        return $this;
    }

    public function getExpediteurNom(): string
    {
        return $this->expediteurNom;
    }

    public function setExpediteurNom(string $expediteurNom): static
    {
        $this->expediteurNom = $expediteurNom;

        return $this;
    }

    public function getExpediteurEmail(): string
    {
        return $this->expediteurEmail;
    }

    public function setExpediteurEmail(string $expediteurEmail): static
    {
        $this->expediteurEmail = $expediteurEmail;

        return $this;
    }

    public function getConfigChiffree(): string
    {
        return $this->configChiffree;
    }

    public function setConfigChiffree(string $configChiffree): static
    {
        $this->configChiffree = $configChiffree;

        return $this;
    }
}
