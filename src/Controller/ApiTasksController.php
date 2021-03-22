<?php

namespace App\Controller;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Webmozart\Assert\Assert;

/**
 * @Route(path="/api/tasks", name="api_tasks_")
 */
class ApiTasksController extends AbstractController
{
    /**
     * @Route("", name="view_all", methods={"GET"})
     */
    public function viewAllTasks(TaskRepository $taskRepo, SerializerInterface $serializer): JsonResponse
    {
        $user = $this->getUser();
        Assert::isInstanceOf($user, User::class);

        $tasks = $taskRepo->findBy(['user' => $user]);

        $context = new SerializationContext();
        $context->setGroups(['tasks']);

        return (new JsonResponse())->setContent($serializer->serialize($tasks, 'json', $context));
    }

    /**
     * @Route("/{id}", name="view_task", methods={"GET"})
     */
    public function viewTask(int $id, TaskRepository $taskRepo, SerializerInterface $serializer): JsonResponse
    {
        $user = $this->getUser();
        Assert::isInstanceOf($user, User::class);

        $task = $taskRepo->findBy(['id' => $id, 'user' => $user]);

        $context = new SerializationContext();
        $context->setGroups(['tasks']);

        return (new JsonResponse())->setContent($serializer->serialize($task, 'json', $context));
    }

    /**
     * @Route("", name="add", methods={"POST"})
     */
    public function addTask(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $request = $this->convertJsonData($request);

        if (!$request->request->get('title')) {
            return $this->json(['error' => 'Invalid data.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->getUser();
        Assert::isInstanceOf($user, User::class);

        $task = (new Task())
            ->setTitle($request->request->get('title'))
            ->setUser($user);

        $em->persist($task);
        $em->flush();

        return $this->json(['success' => 'Task created.'], Response::HTTP_CREATED);
    }

    /**
     * @Route("/{id}", name="edit_task", methods={"PUT"})
     */
    public function editTask(Request $request, EntityManagerInterface $em, int $id): JsonResponse
    {
        $request = $this->convertJsonData($request);

        if (!$request->request->get('title') || !$request->request->get('completed')) {
            return $this->json(['error' => 'Invalid data.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->getUser();
        Assert::isInstanceOf($user, User::class);

        $task = $em->getRepository(Task::class)->findOneBy(['id' => $id, 'user' => $user]);

        if (null === $task) {
            return $this->json(['error' => 'Task not found.'], Response::HTTP_NOT_FOUND);
        }

        $task
            ->setTitle($request->request->get('title'))
            ->setCompleted($request->request->get('completed'));

        $em->flush();

        return $this->json(['success' => 'Task edited.'], Response::HTTP_OK);
    }

    /**
     * @Route("/{id}", name="delete", methods={"DELETE"})
     */
    public function deleteTask(EntityManagerInterface $em, int $id): JsonResponse
    {
        $task = $em->find(Task::class, $id);

        if (null === $task) {
            return $this->json(['error' => 'Task not found.'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();
        Assert::isInstanceOf($user, User::class);

        if ($task->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Invalid data.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $em->remove($task);
        $em->flush();

        return $this->json(['success' => 'Task deleted.'], Response::HTTP_OK);
    }

    private function convertJsonData(Request $request): Request
    {
        $data = \json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if ($data === null) {
            return $request;
        }

        $request->request->replace($data);

        return $request;
    }
}
