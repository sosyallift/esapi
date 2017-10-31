<?php
namespace Everywhere\Api;

use Everywhere\Api\Auth\AuthenticationAdapter;
use Everywhere\Api\Auth\AuthenticationService;
use Everywhere\Api\Auth\IdentityStorage;
use Everywhere\Api\Auth\TokenBuilder;
use Everywhere\Api\Contract\App\ContainerInterface;
use Everywhere\Api\Contract\Auth\AuthenticationAdapterInterface;
use Everywhere\Api\Contract\Auth\AuthenticationServiceInterface;
use Everywhere\Api\Contract\Auth\IdentityStorageInterface;
use Everywhere\Api\Contract\Auth\TokenBuilderInterface;
use Everywhere\Api\Contract\Schema\DataLoaderFactoryInterface;
use Everywhere\Api\Middleware\AuthMiddleware;
use Everywhere\Api\Middleware\GraphQLMiddleware;
use Everywhere\Api\Middleware\AuthenticationMiddleware;
use Everywhere\Api\Schema\DataLoaderFactory;
use Everywhere\Api\Schema\Builder;
use Everywhere\Api\Schema\Resolvers\MutationResolver;
use Everywhere\Api\Schema\TypeDecorator;
use Everywhere\Api\Contract\Schema\BuilderInterface;
use Everywhere\Api\Contract\Schema\TypeConfigDecoratorInterface;
use GraphQL\Server\ServerConfig;
use GraphQL\Executor\Promise\PromiseAdapter;
use Overblog\DataLoader\Promise\Adapter\Webonyx\GraphQL\SyncPromiseAdapter;
use Everywhere\Api\Schema\Resolvers\QueryResolver;
use Everywhere\Api\Schema\Resolvers\UserResolver;
use Everywhere\Api\Schema\Resolvers\PhotoResolver;
use Everywhere\Api\Schema\Resolvers\CommentResolver;

return [
    PromiseAdapter::class => function() {
        return new SyncPromiseAdapter();
    },

    ServerConfig::class => function(ContainerInterface $container) {
        /**
         * @var $authService AuthenticationServiceInterface
         */
        $authService = $container[AuthenticationServiceInterface::class];

        return ServerConfig::create([
            "context" => function() use($authService) {
                return [
                    "viewer" => $authService->hasIdentity() ? $authService->getIdentity()->userId : null
                ];
            },
            "debug" => true,
            "schema" => $container[BuilderInterface::class]->build(),
            "promiseAdapter" => $container[PromiseAdapter::class]
        ]);
    },

    BuilderInterface::class => function(ContainerInterface $container) {
        return new Builder(
            $container->getSettings()["schema"]["path"],
            $container[TypeConfigDecoratorInterface::class]
        );
    },

    GraphQLMiddleware::class => function(ContainerInterface $container) {
        return new GraphQLMiddleware(
            $container[ServerConfig::class]
        );
    },

    TypeConfigDecoratorInterface::class => function(ContainerInterface $container) {
        return new TypeDecorator(
            $container->getSettings()["schema"]["resolvers"],
            function($resolverClass) use ($container) {
                return $container->has($resolverClass) ? $container[$resolverClass] : null;
            }
        );
    },

    DataLoaderFactoryInterface::class => function(ContainerInterface $container) {
        return new DataLoaderFactory(
            $container[PromiseAdapter::class]
        );
    },

    IdentityStorageInterface::class => function(ContainerInterface $container) {
        return new IdentityStorage();
    },

    AuthenticationAdapterInterface::class => function(ContainerInterface $container) {
        return new AuthenticationAdapter(
            $container->getIntegration()->getUsersRepository()
        );
    },

    AuthenticationServiceInterface::class => function(ContainerInterface $container) {
        return new AuthenticationService(
            $container[IdentityStorageInterface::class],
            $container[AuthenticationAdapterInterface::class]
        );
    },

    AuthenticationMiddleware::class => function(ContainerInterface $container) {
        return new AuthenticationMiddleware(
            $container->getSettings()["jwt"],
            $container[IdentityStorageInterface::class],
            $container[TokenBuilderInterface::class]
        );
    },

    TokenBuilderInterface::class => function(ContainerInterface $container) {
        return new TokenBuilder(
            $container->getSettings()["jwt"]
        );
    },

    // Resolvers

    QueryResolver::class => function(ContainerInterface $container) {
        return new QueryResolver($container->getIntegration()->getUsersRepository());
    },

    UserResolver::class => function(ContainerInterface $container) {
        return new UserResolver(
            $container->getIntegration()->getUsersRepository(),
            $container[DataLoaderFactoryInterface::class]
        );
    },

    PhotoResolver::class => function(ContainerInterface $container) {
        return new PhotoResolver(
            $container->getIntegration()->getPhotoRepository(),
            $container[DataLoaderFactoryInterface::class]
        );
    },

    CommentResolver::class => function(ContainerInterface $container) {
        return new CommentResolver(
            $container->getIntegration()->getCommentsRepository(),
            $container[DataLoaderFactoryInterface::class]
        );
    },

    MutationResolver::class => function(ContainerInterface $container) {
        return new MutationResolver(
            $container[AuthenticationServiceInterface::class],
            $container[TokenBuilderInterface::class]
        );
    },
];
