<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DemoNavigationContractTest extends TestCase
{
    public function test_web_navigation_uses_mvp_route_whitelists()
    {
        $view = file_get_contents(__DIR__.'/../../resources/views/partials/portal-navbar.blade.php');

        $this->assertStringContainsString("$".'userMainRoutes', $view);
        $this->assertStringContainsString("$".'adminMainRoutes', $view);
        $this->assertStringContainsString("$"."label === 'Usuario'", $view);
        $this->assertStringContainsString("$"."label === 'Admin'", $view);
        $this->assertStringContainsString("'/web/profile-objectives'", $view);
        $this->assertStringContainsString("'/web/family-group'", $view);
        $this->assertStringContainsString("'/admin-web/supermarket-scraping'", $view);
        $this->assertStringContainsString("'/admin-web/imported-recipes'", $view);
    }
}
