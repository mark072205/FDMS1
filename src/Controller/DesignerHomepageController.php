<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DesignerHomepageController extends AbstractController
{
    #[Route('/designer', name: 'app_designer_homepage')]
    public function index(): Response
    {
        $response = $this->render('designer/designer_homepage/index.html.twig', [
            'controller_name' => 'DesignerHomepageController',
        ]);
        
        // Prevent caching to avoid back button issues after logout
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/designer/homepage', name: 'app_designer_homepage_redirect')]
    public function redirectToHomepage(): RedirectResponse
    {
        return $this->redirectToRoute('app_designer_homepage');
    }
}
