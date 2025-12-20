<?php
declare(strict_types=1);

namespace App\Bootstrap;

use DI\ContainerBuilder;
use PDO;
use Psr\Container\ContainerInterface;
use App\Infrastructure\Database\PdoFactory;
use App\Domain\User\UserRepositoryInterface;
use App\Infrastructure\Repository\PdoUserRepository;
use App\Service\UserService;

/**
 * Dependency Injection container configuration.
 */
class ContainerFactory
{
    public static function create(): ContainerInterface
    {
        $builder = new ContainerBuilder();

        $builder->addDefinitions([
            // Database
            PDO::class => function () {
                return PdoFactory::create();
            },

            // Repositories
            UserRepositoryInterface::class => function (ContainerInterface $c) {
                return new PdoUserRepository($c->get(PDO::class));
            },

            // Services
            UserService::class => function (ContainerInterface $c) {
                return new UserService($c->get(UserRepositoryInterface::class));
            },
        ]);

        return $builder->build();
    }
}
