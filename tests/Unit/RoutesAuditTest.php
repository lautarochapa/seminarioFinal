<?php

namespace Tests\Unit;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * Transversal route audits:
 * 1. All api/v1 routes have no duplicate method+URI.
 * 2. Protected api/v1 routes carry the api_token middleware.
 * 3. The 5 public auth endpoints are NOT protected by api_token.
 * 4. No api/v1 routes leak from routes/web.php (non-versionadas).
 * 5. Wildcard {healthPreferenceType} is constrained to the 3 accepted values.
 */
class RoutesAuditTest extends TestCase
{
    private function apiV1Routes(): array
    {
        return array_filter(
            RouteFacade::getRoutes()->getRoutes(),
            function (Route $r) {
                return str_starts_with($r->uri(), 'api/v1/');
            }
        );
    }

    private function publicAuthUris(): array
    {
        return [
            'api/v1/auth/register',
            'api/v1/auth/login',
            'api/v1/auth/google',
            'api/v1/auth/forgot-password',
            'api/v1/auth/reset-password',
        ];
    }

    /** @test */
    public function no_duplicate_method_uri_in_api_v1(): void
    {
        $seen = [];
        $duplicates = [];

        foreach ($this->apiV1Routes() as $route) {
            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }
                $key = $method . ':' . $route->uri();
                if (isset($seen[$key])) {
                    $duplicates[] = $key;
                }
                $seen[$key] = true;
            }
        }

        $this->assertEmpty(
            $duplicates,
            "Duplicate method+URI pairs found in api/v1:\n" . implode("\n", array_unique($duplicates))
        );
    }

    /** @test */
    public function all_protected_api_v1_routes_have_api_token_middleware(): void
    {
        $publicUris = $this->publicAuthUris();
        $missing = [];

        foreach ($this->apiV1Routes() as $route) {
            if (in_array($route->uri(), $publicUris, true)) {
                continue;
            }

            $allMiddleware = array_merge(
                $route->middleware(),
                $route->action['middleware'] ?? []
            );

            $hasApiToken = false;
            foreach ($allMiddleware as $mw) {
                if ($mw === 'api_token' || str_starts_with((string) $mw, 'api_token')) {
                    $hasApiToken = true;
                    break;
                }
            }

            if (!$hasApiToken) {
                $methods = implode('|', array_diff($route->methods(), ['HEAD']));
                $missing[] = "$methods {$route->uri()}";
            }
        }

        $this->assertEmpty(
            $missing,
            "Protected api/v1 routes missing api_token middleware:\n" . implode("\n", $missing)
        );
    }

    /** @test */
    public function public_auth_endpoints_do_not_carry_api_token(): void
    {
        $publicUris = $this->publicAuthUris();

        foreach ($this->apiV1Routes() as $route) {
            if (!in_array($route->uri(), $publicUris, true)) {
                continue;
            }

            $mw = $route->middleware();

            $this->assertNotContains(
                'api_token',
                $mw,
                "Public endpoint {$route->uri()} should NOT carry api_token middleware."
            );
        }
    }

    /** @test */
    public function no_api_v1_routes_exposed_from_web_without_api_prefix(): void
    {
        $leaked = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();

            // Must NOT have the /api prefix when loaded from web context.
            // A route from web.php for api_contract endpoints would appear
            // without the /api prefix — e.g. "auth/register" instead of "api/v1/auth/register".
            if (
                !str_starts_with($uri, 'api/')
                && (
                    str_starts_with($uri, 'auth/')
                    || str_starts_with($uri, 'admin/users')
                    || str_starts_with($uri, 'family-groups/')
                )
            ) {
                $methods = implode('|', array_diff($route->methods(), ['HEAD']));
                $leaked[] = "$methods $uri";
            }
        }

        $this->assertEmpty(
            $leaked,
            "Non-versioned api_contract routes leaked from routes/web.php:\n" . implode("\n", $leaked)
        );
    }

    /** @test */
    public function health_preference_wildcard_is_constrained_to_accepted_values(): void
    {
        $acceptedPattern = 'dietary-restrictions|health-conditions|allergies';

        foreach ($this->apiV1Routes() as $route) {
            $uri = $route->uri();

            if (
                !str_contains($uri, '{healthPreferenceType}')
                && !str_contains($uri, 'dietary-restrictions')
                && !str_contains($uri, 'health-conditions')
                && !str_contains($uri, 'allergies')
            ) {
                continue;
            }

            if (!str_contains($uri, '{healthPreferenceType}')) {
                continue;
            }

            $wheres = $route->wheres ?? [];

            $this->assertArrayHasKey(
                'healthPreferenceType',
                $wheres,
                "Route [{$uri}] uses \{healthPreferenceType\} but has no ->where() constraint."
            );

            $this->assertEquals(
                $acceptedPattern,
                $wheres['healthPreferenceType'],
                "Route [{$uri}] \{healthPreferenceType\} constraint does not match expected pattern."
            );
        }
    }

    /** @test */
    public function api_v1_routes_count_is_within_expected_range(): void
    {
        $count = count($this->apiV1Routes());

        // Documented: 473 routes under api/v1.
        // Allow ±10 for future additions or test environment variations.
        $this->assertGreaterThanOrEqual(
            460,
            $count,
            "Fewer api/v1 routes than expected ($count < 460). Routes may have been lost in modularization."
        );

        $this->assertLessThanOrEqual(
            520,
            $count,
            "More api/v1 routes than expected ($count > 520). Potential unintended duplicates."
        );
    }
}
