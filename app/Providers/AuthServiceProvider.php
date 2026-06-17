<?php

namespace App\Providers;

use App\Models\Usuario;
use App\Support\GenteSudoGlobalView;
use App\Support\RbacResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        /**
         * Bypass de escopo (tenant): whitelist .env (super_admin) ou RBAC (escala.override.sudo_grade em GLOBAL_SEMED + âncora).
         * A API exige ainda o cabeçalho configurado em gente.sudo_global_view (GenteSudoGlobalView) para ativar a lista completa.
         */
        Gate::define('bypass-tenant', function (?Authenticatable $user): bool {
            if (! $user instanceof Usuario) {
                return false;
            }
            if (! GenteSudoGlobalView::isEnabledInConfig()) {
                return false;
            }
            if (GenteSudoGlobalView::usuarioNaWhitelistInviolavel($user)) {
                return true;
            }

            return RbacResolver::possuiCapacidadeBreakGlassRbac((int) $user->getAttribute('USUARIO_ID'));
        });
    }
}
