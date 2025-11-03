<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientSavedDesignersController extends AbstractController
{
    #[Route('/client/saved/designers', name: 'app_client_saved_designers')]
    public function index(): Response
    {
        return $this->render('client/client_saved_designers/index.html.twig', [
            'controller_name' => 'ClientSavedDesignersController',
        ]);
    }
}
