<?php

namespace App\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LandingPageController extends AbstractController
{
    #[Route('/', name: 'app_landing_page')]
    public function index(CategoryRepository $categoryRepository): Response
    {
        return $this->render('landing_page/index.html.twig', [
            'controller_name' => 'LandingPageController',
            'categories' => $categoryRepository->findAll(),
        ]);
    }

    #[Route('/about', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('landing_page/about.html.twig');
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(): Response
    {
        return $this->render('landing_page/contact.html.twig');
    }

    #[Route('/category/{id}', name: 'app_category_view')]
    public function viewCategory(Category $category): Response
    {
        return $this->render('landing_page/category_view.html.twig', [
            'category' => $category,
            'projects' => $category->getProjects(),
        ]);
    }
}
