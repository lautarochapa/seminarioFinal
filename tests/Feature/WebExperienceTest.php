<?php
namespace Tests\Feature;

use App\User;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_panel_redirects_but_public_and_email_routes_remain_accessible()
    {
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Linux; Android 14)']);
        foreach (['/login', '/register', '/web', '/web/stock', '/admin-web'] as $path) {
            $this->get($path)->assertRedirect('/abrir-app');
        }
        foreach (['/', '/abrir-app', '/password/reset', '/password/reset/sample-token?email=test@example.invalid'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_iphone_panel_uses_same_entry_page()
    {
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)'])
            ->get('/login')->assertRedirect('/abrir-app');
        $this->get('/abrir-app')->assertOk()->assertSee('data-ios-entry', false);
    }

    public function test_client_hint_detects_mobile_without_specific_user_agent()
    {
        $this->withHeaders(['Sec-CH-UA-Mobile' => '?1'])->get('/web')->assertRedirect('/abrir-app');
    }

    public function test_mobile_invitation_preserves_login_and_registration()
    {
        $this->withHeaders(['User-Agent' => 'Android']);
        $this->get('/web/family-group?invitation=12')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('invitation=12', false);
        $this->get('/register')->assertOk()->assertSee('data-mobile-invitation="1"', false);
        $this->get('/web/stock')->assertRedirect('/abrir-app');
    }

    public function test_expired_or_invalid_invitation_does_not_open_panel()
    {
        $this->withHeaders(['User-Agent' => 'Android']);
        $this->withSession(['mobile_invitation_until' => time() - 1])->get('/login')->assertRedirect('/abrir-app');
        $this->get('/web/family-group?invitation=abc')->assertRedirect('/abrir-app');
    }

    public function test_desktop_auth_forms_share_fixed_labels_without_autofocus()
    {
        foreach (['/login', '/register', '/password/reset', '/password/reset/token'] as $path) {
            $this->get($path)->assertOk()->assertSee('auth-field', false)->assertDontSee('autofocus', false);
        }
    }

    public function test_common_user_has_direct_navigation_and_no_api_panel()
    {
        $user = factory(User::class)->create();
        $user->roles()->sync([Role::where('code', 'user')->firstOrFail()->id]);
        $response = $this->actingAs($user)->get('/web/profile-objectives');
        $response->assertOk()->assertDontSee('Sesion API')->assertSee('js/panel-ui.js', false);
        foreach (['Inicio', 'Mi cocina', 'Recetas', 'Plan', 'Compras', 'Presupuesto', 'Grupo familiar'] as $label) {
            $response->assertSee($label);
        }
    }

    public function test_landing_uses_product_overview_before_demos_and_clean_download_label()
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'id="producto"'), strpos($html, 'Pensada para la vida real'));
        if (strpos($html, 'id="demos"') !== false) {
            $this->assertLessThan(strpos($html, 'id="demos"'), strpos($html, 'mobile-home-preview-martin.jpg'));
        }
        $this->assertStringNotContainsString('Ver el recorrido en video', $html);
        $this->assertStringNotContainsString('En pruebas', $html);
        $this->assertStringNotContainsString('versión de prueba', $html);
    }
}
