<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;

use App\Data\ChangePasswordData;
use App\Http\Controllers\Controller;
use App\Models\Tenant;

use App\UseCases\LegacyChangePasswordUseCase;


class LegacyChangePasswordController extends Controller
{
    public function __construct(
        protected LegacyChangePasswordUseCase $legacyChangePasswordUseCase,
    ) {
    }

    public function __invoke(Request $request, ChangePasswordData $changePasswordData)
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        // TODO: repeated from LegacyLoginController - move to a shared middleware?
        if ($tenant && $tenant->sso_required) {
            return [
                'success' => false,
                'error'   => 'tenant_requires_sso',
            ];
        }

        $user = $request->user();
        $user = $request->attributes->get('authenticated_user');

        return $this->legacyChangePasswordUseCase->execute($user, $changePasswordData);
    }
}

