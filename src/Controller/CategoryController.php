<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/category')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {}
    #[Route(name: 'app_category_index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository): Response
    {
        $response = $this->render('admin_staff/category/index.html.twig', [
            'categories' => $categoryRepository->findAll(),
        ]);
        
        // Prevent caching
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/new', name: 'app_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($category);
            $entityManager->flush();

            // Manually log the creation (fallback if event subscriber doesn't fire)
            try {
                $this->activityLogService->log(
                    'CREATE',
                    'Category',
                    $category->getId(),
                    "Created Category: " . ($category->getName() ?? 'Unknown')
                );
            } catch (\Exception $e) {
                error_log('Failed to log category creation: ' . $e->getMessage());
            }

            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        $response = $this->render('admin_staff/category/new.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
        
        // Prevent caching
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/{id}', name: 'app_category_show', methods: ['GET'])]
    public function show(Category $category): Response
    {
        $response = $this->render('admin_staff/category/show.html.twig', [
            'category' => $category,
        ]);
        
        // Prevent caching
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/{id}/edit', name: 'app_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Manually log the update (fallback if event subscriber doesn't fire)
            try {
                $this->activityLogService->log(
                    'UPDATE',
                    'Category',
                    $category->getId(),
                    "Updated Category: " . ($category->getName() ?? 'Unknown')
                );
            } catch (\Exception $e) {
                error_log('Failed to log category update: ' . $e->getMessage());
            }

            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        $response = $this->render('admin_staff/category/edit.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
        
        // Prevent caching
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/{id}', name: 'app_category_delete', methods: ['POST'])]
    public function delete(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$category->getId(), $request->getPayload()->getString('_token'))) {
            $categoryId = $category->getId();
            $categoryName = $category->getName() ?? 'Unknown';

            // Log deletion before removing (preRemove event will also fire, but this ensures it's logged)
            try {
                $this->activityLogService->log(
                    'DELETE',
                    'Category',
                    $categoryId,
                    "Deleted Category: " . $categoryName
                );
            } catch (\Exception $e) {
                error_log('Failed to log category deletion: ' . $e->getMessage());
            }

            $entityManager->remove($category);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
    }
}
