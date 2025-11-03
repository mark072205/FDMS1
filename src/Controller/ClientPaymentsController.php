<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientPaymentsController extends AbstractController
{
    #[Route('/client/payments', name: 'app_client_payments')]
    public function index(): Response
    {
        return $this->render('client/client_payments/index.html.twig', [
            'controller_name' => 'ClientPaymentsController',
        ]);
    }
}
