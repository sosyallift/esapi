<?php
/**
 * Created by PhpStorm.
 * User: skambalin
 * Date: 19.10.17
 * Time: 15.16
 */

namespace Everywhere\Api\Schema\Resolvers;

use Everywhere\Api\Contract\Integration\UsersRepositoryInterface;
use Everywhere\Api\Contract\Schema\ContextInterface;
use Everywhere\Api\Contract\Schema\ResolverInterface;
use Everywhere\Api\Schema\CompositeResolver;
use GraphQL\Type\Definition\ResolveInfo;

class QueryResolver extends CompositeResolver
{
    public function __construct(UsersRepositoryInterface $usersRepository)
    {
        parent::__construct();

        $this->addFieldResolver("me", function($root, $args, ContextInterface $context) {
            return $context->getViewer()->getUserId();
        });

        $this->addFieldResolver("users", function($root, $args) use($usersRepository) {
            return $usersRepository->findAllIds($args);
        });

        $this->addFieldResolver("user", function($root, $args) use($usersRepository) {
            $users = $usersRepository->findByIds([$args["id"]]);
            $user = reset($users);

            return $user ?: null;
        });
    }
}
