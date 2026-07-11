<?php

declare(strict_types=1);

use App\Access\Application\AccessDecision;
use App\Access\Domain\Permission;
use App\Access\Presentation\Http\Middleware\RequirePermissionMiddleware;
use App\Auth\Presentation\Http\Action\Account\ChangeEmailAction;
use App\Auth\Presentation\Http\Action\Account\ChangeLoginAction;
use App\Auth\Presentation\Http\Action\Account\ChangePasswordAction;
use App\Auth\Presentation\Http\Action\Users\BlockUserAction;
use App\Auth\Presentation\Http\Action\Users\CreateUserAction;
use App\Auth\Presentation\Http\Action\Users\DeleteUserAction;
use App\Auth\Presentation\Http\Action\Users\GetUserAction;
use App\Auth\Presentation\Http\Action\Users\ListUsersAction;
use App\Auth\Presentation\Http\Action\Users\UnblockUserAction;
use App\Auth\Presentation\Http\Action\Users\UpdateUserAction;
use App\Auth\Presentation\Http\Middleware\AuthenticationMiddleware;
use App\Http\Action\Auth\ConfirmRegistrationAction;
use App\Http\Action\Auth\CurrentUserAction;
use App\Http\Action\Auth\LoginAction;
use App\Http\Action\Auth\LogoutAction;
use App\Http\Action\Auth\RequestRegistrationCodeAction;
use App\Http\Action\HealthAction;
use App\Http\Action\LivenessAction;
use Slim\App;

return static function(App $app): void {
    $app->get('/health', HealthAction::class);
    $app->get('/health/liveness', LivenessAction::class);

    $app->group('/api/v1/auth', function($group): void {
        $group->post('/login', LoginAction::class);
        $group->post('/register/request-code', RequestRegistrationCodeAction::class);
        $group->post('/register/confirm', ConfirmRegistrationAction::class);
        $group->post('/logout', LogoutAction::class);
        $group->get('/user', CurrentUserAction::class);
    });

    // Управление своей учётной записью — за Bearer-аутентификацией.
    $app->group('/api/v1/account', function($group): void {
        $group->post('/change-password', ChangePasswordAction::class);
        $group->post('/change-login', ChangeLoginAction::class);
        $group->post('/change-email', ChangeEmailAction::class);
    })->add(AuthenticationMiddleware::class);

    // Управление пользователями — требует права users.manage (RBAC) поверх аутентификации.
    $container = $app->getContainer();
    $app->group('/api/v1/users', function($group): void {
        $group->get('', ListUsersAction::class);
        $group->post('', CreateUserAction::class);
        $group->get('/{id:[0-9]+}', GetUserAction::class);
        $group->patch('/{id:[0-9]+}', UpdateUserAction::class);
        $group->post('/{id:[0-9]+}/block', BlockUserAction::class);
        $group->post('/{id:[0-9]+}/unblock', UnblockUserAction::class);
        $group->delete('/{id:[0-9]+}', DeleteUserAction::class);
    })
        ->add(new RequirePermissionMiddleware($container->get(AccessDecision::class), Permission::UsersManage))
        ->add(AuthenticationMiddleware::class)
    ;
};
