<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\User;

use Doctrine\ORM\Event\LifecycleEventArgs;
use AppBundle\Service\TokenGeneratorInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

class UserListener
{
    /**
     * @var EncoderFactoryInterface
     */
    protected $encoderFactory;

    /**
     * @var TokenGeneratorInterface
     */
    protected $tokenGenerator;

    /**
     * Listener constructor
     *
     * @param EncoderFactoryInterface $encoderFactory Encoder factory
     * @param TokenGeneratorInterface $tokenGenerator Token generator
     */
    public function __construct(EncoderFactoryInterface $encoderFactory, TokenGeneratorInterface $tokenGenerator)
    {
        $this->encoderFactory = $encoderFactory;
        $this->tokenGenerator = $tokenGenerator;
    }

    /**
     * Handle pre persist event
     *
     * @param LifecycleEventArgs $args  Event arguments
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        $this->preUpdate($args);
    }

    /**
     * Handle pre update event
     *
     * @param LifecycleEventArgs $args  Event arguments
     */
    public function preUpdate(LifecycleEventArgs $args)
    {
        $user = $args->getObject();

        if (! $user instanceof User) {
            return ;
        }

        // generate salt
        $salt = $user->getSalt();
        if (empty($salt)) {
            $salt = $this->tokenGenerator->getToken();

            $user->setSalt($salt);
        }

        // encode user password if needle
        $plainPassword = $user->getPlainPassword();
        if (! empty($plainPassword)) {
            $password = $this
                ->encoderFactory
                ->getEncoder($user)
                ->encodePassword($plainPassword, $salt);

            $user->setPassword($password);
        }
    }
}