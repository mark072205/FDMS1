<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientHomepageController extends AbstractController
{
    #[Route('/client', name: 'app_client_homepage')]
    public function index(): Response
    {
        $response = $this->render('client/client_homepage/index.html.twig', [
            'controller_name' => 'ClientHomepageController',
        ]);
        
        // Prevent caching to avoid back button issues after logout
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/client/homepage', name: 'app_client_homepage_redirect')]
    public function redirectToHomepage(): RedirectResponse
    {
        return $this->redirectToRoute('app_client_homepage');
    }
}
