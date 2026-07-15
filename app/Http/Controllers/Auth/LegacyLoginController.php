<?php

namespace App\Http\Controllers\Auth;

use App\Data\PostLoginData;
use App\Http\Controllers\Controller;
use App\Models\Tenant;

use App\UseCases\LegacyLoginUseCase;


class LegacyLoginController extends Controller
{
    public function __construct(
        protected LegacyLoginUseCase $legacyLoginUseCase,
    ) {
    }

    public function __invoke(PostLoginData $postLoginData)
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        // Tenants flagged sso_required reject password login for everyone, regardless
        // of whether the specific user has finished SSO linking yet.
        if ($tenant && $tenant->sso_required) {
            return [
                'success' => false,
                'error'   => 'tenant_requires_sso',
            ];
        }

        return $this->legacyLoginUseCase->execute($postLoginData);
    }
}
