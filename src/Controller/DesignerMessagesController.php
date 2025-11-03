<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DesignerMessagesController extends AbstractController
{
    #[Route('/designer/messages', name: 'app_designer_messages')]
    public function index(): Response
    {
        return $this->render('designer/designer_messages/index.html.twig', [
            'controller_name' => 'DesignerMessagesController',
        ]);
    }
}
