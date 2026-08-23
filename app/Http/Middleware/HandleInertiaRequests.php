<?php

namespace App\Http\Middleware;

use App\Http\Resources\UserResource;
use App\Models\SystemUsage;
use App\Models\TokenUsageLog;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $systemUsage = SystemUsage::getUsage();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? (new UserResource($request->user()))->resolve($request) : null,
            ],
            'api_status' => $systemUsage->apiStatusArray(),
            'token_usage' => [
                'total_tokens' => TokenUsageLog::totalTokens(),
            ],
        ];
    }
}
