<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DesignerProjectsController extends AbstractController
{
    #[Route('/designer/projects', name: 'app_designer_projects')]
    public function index(): Response
    {
        return $this->render('designer/designer_projects/index.html.twig', [
            'controller_name' => 'DesignerProjectsController',
        ]);
    }
}
