<?php
declare(strict_types=1);

namespace App\Bootstrap;

use DI\ContainerBuilder;
use PDO;
use Psr\Container\ContainerInterface;
use App\Infrastructure\Database\PdoFactory;
use App\Shared\Domain\User\UserRepositoryInterface;
use App\Shared\Infrastructure\Repository\PdoUserRepository;
use App\Shared\Service\UserService;
use App\Collections\Domain\Film\FilmRepositoryInterface;
use App\Infrastructure\Repository\PdoFilmRepository;
use App\Infrastructure\Repository\PdoEditionRepository;
use App\Collections\Service\ImportService;
use App\Collections\Infrastructure\Repository\ImportRepository;
use App\Collections\Infrastructure\Parser\DvdProfilerXmlParser;

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

            // Import infrastructure
            DvdProfilerXmlParser::class => function () {
                return new DvdProfilerXmlParser();
            },

            ImportRepository::class => function (ContainerInterface $c) {
                return new ImportRepository($c->get(PDO::class));
            },

            ImportService::class => function (ContainerInterface $c) {
                return new ImportService(
                    $c->get(DvdProfilerXmlParser::class),
                    $c->get(ImportRepository::class)
                );
            },
        ]);

        return $builder->build();
    }
}
