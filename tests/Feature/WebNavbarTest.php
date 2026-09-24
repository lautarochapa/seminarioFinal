<?php

namespace Tests\Feature;

use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebNavbarTest extends TestCase
{
    use RefreshDatabase;

    private function headerLink($html)
    {
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        return $xpath->query('//header//a[contains(concat(" ", normalize-space(@class), " "), " logo ")]')->item(0);
    }

    public function test_guest_logo_stays_on_public_home()
    {
        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertSame(url('/'), $this->headerLink($html)->getAttribute('href'));
    }

    public function test_signed_in_logo_links_to_dashboard_for_each_role()
    {
        foreach (['user' => '/web', 'super_admin' => '/admin-web', 'catalog_admin' => '/admin-web', 'recipe_admin' => '/admin-web'] as $role => $home) {
            $user = factory(User::class)->create();
            $user->roles()->sync([Role::where('code', $role)->firstOrFail()->id]);
            $screen = $role === 'user' ? '/web/stock' : $home;
            $html = $this->actingAs($user)->get($screen)->assertOk()->getContent();
            $this->assertSame(url($home), $this->headerLink($html)->getAttribute('href'), $role);
            $this->assertStringContainsString('navbar-user-name', $html);
            if ($role === 'user') {
                $this->get($home)->assertRedirect('/web/onboarding');
            } else {
                $this->get($home)->assertOk();
            }
            $landing = $this->get('/')->assertOk()->getContent();
            $this->assertSame(url($home), $this->headerLink($landing)->getAttribute('href'), $role.' on landing');
        }
    }
}
