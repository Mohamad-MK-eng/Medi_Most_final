<?php

namespace App\Providers;

use Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    public function boot()
    {
        $this->registerPolicies();

        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));





    $this->registerPolicies();

    Gate::before(function ($user, $ability) {
        if ($user->hasRole('admin')) {
            return true;
        }
    });

    foreach (['secretary', 'doctor', 'patient'] as $role) {
        Gate::define($role, function ($user) use ($role) {
            return $user->hasRole($role);
        });
    }
}


    }

