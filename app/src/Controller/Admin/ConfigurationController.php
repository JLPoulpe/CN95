<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/configuration')]
class ConfigurationController extends AbstractController
{
    #[Route('/activites', name: 'admin_configuration_activites')]
    public function activites(): Response
    {
        return $this->render('admin/configuration/activites.html.twig');
    }
}
