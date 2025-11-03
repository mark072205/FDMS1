<?php

namespace App\Controller;

use App\Repository\UsersRepository;
use App\Repository\ProjectRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/search')]
final class SearchController extends AbstractController
{
    #[Route('/users', name: 'app_search_users', methods: ['GET'])]
    public function searchUsers(Request $request, UsersRepository $usersRepository): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (empty($query) || strlen($query) < 2) {
            return new JsonResponse(['results' => []]);
        }

        $users = $usersRepository->createQueryBuilder('u')
            ->where('u.username LIKE :query')
            ->orWhere('u.email LIKE :query')
            ->orWhere('u.firstName LIKE :query')
            ->orWhere('u.lastName LIKE :query')
            ->orWhere('u.userType LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.username', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'id' => $user->getId(),
                'type' => 'user',
                'title' => $user->getFirstName() . ' ' . $user->getLastName(),
                'subtitle' => '@' . $user->getUsername() . ' • ' . $user->getEmail(),
                'badge' => [
                    'text' => ucfirst($user->getUserType()),
                    'class' => $user->getUserType() === 'admin' ? 'badge-danger' : 
                              ($user->getUserType() === 'designer' ? 'badge-warning' : 'badge-info')
                ],
                'status' => [
                    'text' => $user->isActive() ? 'Active' : 'Disabled',
                    'class' => $user->isActive() ? 'is-active' : 'is-inactive'
                ],
                'url' => $this->generateUrl('app_user_show', ['id' => $user->getId()])
            ];
        }

        return new JsonResponse(['results' => $results]);
    }

    #[Route('/categories', name: 'app_search_categories', methods: ['GET'])]
    public function searchCategories(Request $request, CategoryRepository $categoryRepository): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (empty($query) || strlen($query) < 2) {
            return new JsonResponse(['results' => []]);
        }

        $categories = $categoryRepository->createQueryBuilder('c')
            ->where('c.name LIKE :query')
            ->orWhere('c.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.name', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $results = [];
        foreach ($categories as $category) {
            $results[] = [
                'id' => $category->getId(),
                'type' => 'category',
                'title' => $category->getName(),
                'subtitle' => $category->getDescription() ?: 'No description',
                'badge' => [
                    'text' => 'Category',
                    'class' => 'badge-secondary'
                ],
                'url' => $this->generateUrl('app_category_show', ['id' => $category->getId()])
            ];
        }

        return new JsonResponse(['results' => $results]);
    }

    #[Route('/global', name: 'app_search_global', methods: ['GET'])]
    public function searchGlobal(
        Request $request, 
        UsersRepository $usersRepository,
        ProjectRepository $projectRepository,
        CategoryRepository $categoryRepository
    ): JsonResponse {
        $query = $request->query->get('q', '');
        
        if (empty($query) || strlen($query) < 2) {
            return new JsonResponse(['results' => []]);
        }

        $results = [];

        // Search Users
        $users = $usersRepository->createQueryBuilder('u')
            ->where('u.username LIKE :query')
            ->orWhere('u.email LIKE :query')
            ->orWhere('u.firstName LIKE :query')
            ->orWhere('u.lastName LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.username', 'ASC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($users as $user) {
            $results[] = [
                'id' => $user->getId(),
                'type' => 'user',
                'title' => $user->getFirstName() . ' ' . $user->getLastName(),
                'subtitle' => '@' . $user->getUsername() . ' • ' . $user->getEmail(),
                'badge' => [
                    'text' => ucfirst($user->getUserType()),
                    'class' => $user->getUserType() === 'admin' ? 'badge-danger' : 
                              ($user->getUserType() === 'designer' ? 'badge-warning' : 'badge-info')
                ],
                'url' => $this->generateUrl('app_user_show', ['id' => $user->getId()])
            ];
        }

        // Search Projects
        $projects = $projectRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->where('p.title LIKE :query')
            ->orWhere('p.description LIKE :query')
            ->orWhere('c.name LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('p.title', 'ASC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($projects as $project) {
            $results[] = [
                'id' => $project->getId(),
                'type' => 'project',
                'title' => $project->getTitle(),
                'subtitle' => $project->getCategory() ? $project->getCategory()->getName() : 'No Category',
                'badge' => [
                    'text' => ucfirst($project->getStatus()),
                    'class' => $project->getStatus() === 'completed' ? 'badge-success' : 
                              ($project->getStatus() === 'ongoing' ? 'badge-warning' : 'badge-info')
                ],
                'url' => '#' // TODO: Add project show route when implemented
            ];
        }

        // Search Categories
        $categories = $categoryRepository->createQueryBuilder('c')
            ->where('c.name LIKE :query')
            ->orWhere('c.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.name', 'ASC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($categories as $category) {
            $results[] = [
                'id' => $category->getId(),
                'type' => 'category',
                'title' => $category->getName(),
                'subtitle' => $category->getDescription() ?: 'No description',
                'badge' => [
                    'text' => 'Category',
                    'class' => 'badge-secondary'
                ],
                'url' => $this->generateUrl('app_category_show', ['id' => $category->getId()])
            ];
        }

        return new JsonResponse(['results' => $results]);
    }
}
