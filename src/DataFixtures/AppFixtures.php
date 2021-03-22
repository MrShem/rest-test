<?php

namespace App\DataFixtures;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class AppFixtures extends Fixture
{
    private UserPasswordEncoderInterface $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager)
    {
        $rootUser = (new User())
            ->setUsername('root')
            ->setRoles(['ROLE_API'])
            ->setApiToken('root_key');

        $rootPassword = $this->encoder->encodePassword($rootUser, 'root');
        $rootUser->setPassword($rootPassword);

        $manager->persist($rootUser);

        $otherUser = (new User())
            ->setUsername('other')
            ->setRoles(['ROLE_API'])
            ->setApiToken('other_key');

        $otherPassword = $this->encoder->encodePassword($otherUser, 'other');
        $otherUser->setPassword($otherPassword);

        $manager->persist($otherUser);

        // tasks
        $rootTask = (new Task())
            ->setUser($rootUser)
            ->setTitle('root task');
        $manager->persist($rootTask);

        // tasks
        $secondRootTask = (new Task())
            ->setUser($rootUser)
            ->setTitle('second root task');
        $manager->persist($secondRootTask);

        $otherTask = (new Task())
            ->setUser($otherUser)
            ->setTitle('other task');
        $manager->persist($otherTask);

        $manager->flush();
    }
}
