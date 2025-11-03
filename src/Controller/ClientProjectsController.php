<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientProjectsController extends AbstractController
{
    #[Route('/client/projects', name: 'app_client_projects')]
    public function index(): Response
    {
        $response = $this->render('client/client_projects/index.html.twig', [
            'controller_name' => 'ClientProjectsController',
        ]);
        
        // Prevent caching
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }
}

