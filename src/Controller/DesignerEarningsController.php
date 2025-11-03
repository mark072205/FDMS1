<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DesignerEarningsController extends AbstractController
{
    #[Route('/designer/earnings', name: 'app_designer_earnings')]
    public function index(): Response
    {
        return $this->render('designer/designer_earnings/index.html.twig', [
            'controller_name' => 'DesignerEarningsController',
        ]);
    }
}
