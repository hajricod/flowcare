<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->booted(function () {
            if (! class_exists(Scramble::class)) {
                return;
            }

            Scramble::configure()
                ->withDocumentTransformers(function (OpenApi $openApi) {
                    // Keep security at operation level so each endpoint shows required role credentials.
                    $openApi->security = null;

                    $openApi->components->addSecurityScheme(
                        'basicAuth',
                        SecurityScheme::http('basic')
                            ->as('basicAuth')
                            ->setDescription('HTTP Basic credentials for any active user account.')
                    );
                    $openApi->components->addSecurityScheme(
                        'basicCustomer',
                        SecurityScheme::http('basic')
                            ->as('basicCustomer')
                            ->setDescription('Use CUSTOMER account username/password.')
                    );
                    $openApi->components->addSecurityScheme(
                        'basicStaff',
                        SecurityScheme::http('basic')
                            ->as('basicStaff')
                            ->setDescription('Use STAFF account username/password.')
                    );
                    $openApi->components->addSecurityScheme(
                        'basicBranchManager',
                        SecurityScheme::http('basic')
                            ->as('basicBranchManager')
                            ->setDescription('Use BRANCH_MANAGER account username/password.')
                    );
                    $openApi->components->addSecurityScheme(
                        'basicAdmin',
                        SecurityScheme::http('basic')
                            ->as('basicAdmin')
                            ->setDescription('Use ADMIN account username/password.')
                    );
                })
                ->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo) {
                    $middlewares = $routeInfo->route->gatherMiddleware();

                    $usesBasicAuth = collect($middlewares)
                        ->contains(fn ($m) => is_string($m) && str_starts_with($m, 'auth.basic.custom'));

                    if (! $usesBasicAuth) {
                        return;
                    }

                    $roleMiddleware = collect($middlewares)
                        ->first(fn ($m) => is_string($m) && str_starts_with($m, 'role:'));

                    if (! is_string($roleMiddleware)) {
                        $operation->security = [new SecurityRequirement(['basicAuth' => []])];

                        return;
                    }

                    $roleToScheme = [
                        'CUSTOMER' => 'basicCustomer',
                        'STAFF' => 'basicStaff',
                        'BRANCH_MANAGER' => 'basicBranchManager',
                        'ADMIN' => 'basicAdmin',
                    ];

                    $roles = array_values(array_filter(array_map(
                        fn ($r) => trim($r),
                        explode(',', substr($roleMiddleware, strlen('role:')))
                    )));

                    $operation->security = [];
                    foreach ($roles as $role) {
                        if (isset($roleToScheme[$role])) {
                            $operation->addSecurity(new SecurityRequirement([$roleToScheme[$role] => []]));
                        }
                    }

                    if ($operation->security === []) {
                        $operation->security = [new SecurityRequirement(['basicAuth' => []])];
                    }
                });
        });
    }
}
