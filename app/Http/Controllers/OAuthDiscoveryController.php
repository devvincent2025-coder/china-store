<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * RFC 8414 / RFC 9728 discovery documents. Not required for ChatGPT's Custom GPT Action flow
 * (you paste the /oauth/authorize and /oauth/token URLs into its UI directly), but a spec-aware
 * OAuth or MCP client — Claude's remote "custom connector" being the one we actually care about —
 * can fetch these to configure itself without a human copying URLs around. Passport itself only
 * ever serves the actual /oauth/* endpoints, not these two metadata documents, so they're defined
 * here instead.
 */
class OAuthDiscoveryController extends Controller
{
    public function authorizationServer(): JsonResponse
    {
        $issuer = config('app.url');

        return response()->json([
            'issuer' => $issuer,
            'authorization_endpoint' => "{$issuer}/oauth/authorize",
            'token_endpoint' => "{$issuer}/oauth/token",
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'none'],
            'scopes_supported' => ['orders:place', 'orders:read'],
        ]);
    }

    public function protectedResource(): JsonResponse
    {
        $issuer = config('app.url');

        return response()->json([
            'resource' => "{$issuer}/api/v1",
            'authorization_servers' => [$issuer],
            'scopes_supported' => ['orders:place', 'orders:read'],
            'bearer_methods_supported' => ['header'],
        ]);
    }
}
