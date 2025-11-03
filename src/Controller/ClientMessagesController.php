<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientMessagesController extends AbstractController
{
    #[Route('/client/messages', name: 'app_client_messages')]
    public function index(): Response
    {
        return $this->render('client/client_messages/index.html.twig', [
            'controller_name' => 'ClientMessagesController',
        ]);
    }
}
