<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientProposalsController extends AbstractController
{
    #[Route('/client/proposals', name: 'app_client_proposals')]
    public function index(): Response
    {
        return $this->render('client/client_proposals/index.html.twig', [
            'controller_name' => 'ClientProposalsController',
        ]);
    }
}
