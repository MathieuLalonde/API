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
use App\Domain\Film\FilmRepositoryInterface;
use App\Infrastructure\Repository\PdoFilmRepository;
use App\Infrastructure\Repository\PdoEditionRepository;

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

            FilmRepositoryInterface::class => function (ContainerInterface $c) {
                return new PdoFilmRepository($c->get(PDO::class));
            },

            PdoEditionRepository::class => function (ContainerInterface $c) {
                return new PdoEditionRepository($c->get(PDO::class));
            },

            // Services
            UserService::class => function (ContainerInterface $c) {
                return new UserService($c->get(UserRepositoryInterface::class));
            },
        ]);

        return $builder->build();
    }
}
