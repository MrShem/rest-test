<?php

namespace App\Tests\Controller;

use App\Entity\Task;
use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ApiTasksControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    /**
     * @var \Doctrine\ORM\EntityManager|object|null
     */
    private $em;

    /**
     * @var \JMS\Serializer\Serializer|object|null
     */
    private $serializer;

    /**
     * @var object|\Symfony\Bundle\FrameworkBundle\Routing\Router|null
     */
    private $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient([], ['HTTP_X-AUTH-TOKEN' => 'root_key']);
        $this->client->disableReboot();

        $this->em = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $this->em->beginTransaction();
        $this->em->getConnection()->setAutoCommit(false);

        $this->serializer = $this->client->getContainer()->get('jms_serializer');
        $this->router = $this->client->getContainer()->get('router');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->rollback();
        }
    }


    public function testAddTask(): void
    {
        $title = mt_rand() . '_test_task';

        $data = \json_encode(['title' => $title], JSON_THROW_ON_ERROR);

        $url = $this->router->generate('api_tasks_add');

        $this->client->request('POST', $url, [], [], [], $data);

        self::assertEquals(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        $task = $this->em->getRepository(Task::class)->findOneBy(['title' => $title]);

        self::assertInstanceOf(Task::class, $task);
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function testDeleteTask(): void
    {
        $task = $this->em->find(Task::class, 1);

        $url = $this->router->generate('api_tasks_delete', ['id' => $task->getId()]);

        $this->client->request('DELETE', $url);

        self::assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $task = $this->em->find(Task::class, 1);

        self::assertNull($task);

        $response = $this->client->getResponse();

        $content = \json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertEquals('Task deleted.', $content['success']);

        // not found
        $url = $this->router->generate('api_tasks_delete', ['id' => 677677]);

        $this->client->request('DELETE', $url);

        self::assertEquals(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());

        $response = $this->client->getResponse();

        $content = \json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertEquals('Task not found.', $content['error']);

        // other task
        $task = $this->em->find(Task::class, 3);

        $url = $this->router->generate('api_tasks_delete', ['id' => $task->getId()]);

        $this->client->request('DELETE', $url);

        self::assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());

        $task = $this->em->getRepository(Task::class)->find($task->getId());

        self::assertInstanceOf(Task::class, $task);

        $response = $this->client->getResponse();

        $content = \json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertEquals('Invalid data.', $content['error']);
    }

    public function testViewAllTasks(): void
    {
        $tasks = $this->em->getRepository(Task::class)->findBy(['user' => 1]);

        $url = $this->router->generate('api_tasks_view_all');

        $this->client->request('GET', $url);

        $response = $this->client->getResponse();

        self::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $context = new SerializationContext();
        $context->setGroups(['tasks']);
        $expectedJson = $this->serializer->serialize($tasks, 'json', $context);

        self::assertEquals($expectedJson, $response->getContent());
    }

    public function testViewTask(): void
    {
        $task = $this->em->getRepository(Task::class)->find(1);

        $url = $this->router->generate('api_tasks_view_task', ['id' => $task->getId()]);

        $this->client->request('GET', $url);

        $response = $this->client->getResponse();

        self::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $context = new SerializationContext();
        $context->setGroups(['tasks']);
        $expectedJson = '[' . $this->serializer->serialize($task, 'json', $context) . ']';

        self::assertEquals($expectedJson, $response->getContent());
    }

    public function testEditTask()
    {
        $task = $this->em->find(Task::class, 1);

        $data = [
            'title' => 'edited title',
            'completed' => true
        ];

        $jsonData = \json_encode($data, JSON_THROW_ON_ERROR);

        $url = $this->router->generate('api_tasks_edit_task', ['id' => $task->getId()]);

        $this->client->request('PUT', $url, [], [], [], $jsonData);

        $response = $this->client->getResponse();

        self::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $updatedTask = $this->em->find(Task::class, 1);

        self::assertEquals($data['title'], $updatedTask->getTitle());
        self::assertEquals($data['completed'], $updatedTask->getCompleted());

        // other task
        $task = $this->em->find(Task::class, 3);

        $url = $this->router->generate('api_tasks_edit_task', ['id' => $task->getId()]);

        $this->client->request('PUT', $url, [], [], [], $jsonData);

        $response = $this->client->getResponse();

        self::assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $content = \json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertEquals('Task not found.', $content['error']);

        // not found task

        $url = $this->router->generate('api_tasks_edit_task', ['id' => 23552352]);

        $this->client->request('PUT', $url, [], [], [], $jsonData);

        $response = $this->client->getResponse();

        self::assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $content = \json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertEquals('Task not found.', $content['error']);
    }
}
