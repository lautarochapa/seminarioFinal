<?php

namespace Tests\Feature;

use App\FamilyGroup;
use App\Objective;
use App\User;
use App\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El rol por defecto ('user') debe habilitar las pantallas de usuario final
 * (/web/*) para CUALQUIER usuario creado por los caminos normales:
 *  - registro por API (App\Services\Auth\AuthService)
 *  - registro web legacy (App\Http\Controllers\Auth\RegisterController)
 *  - seeder de demostracion (DemoScenarioSeeder)
 *
 * Fuente de verdad unica: App\User::assignDefaultRole().
 */
class DefaultUserRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /** Pantallas de usuario final que un usuario comun debe poder abrir. */
    private const REQUIRED_SCREENS = [
        'dashboard', 'stock', 'recipes', 'recipe-suggestions', 'planning',
        'shopping-list', 'shopping-session', 'purchases', 'notifications',
        'budget', 'family-group', 'profile-objectives', 'catalog',
        'barcode-scanner', 'supermarkets', 'branches',
    ];

    private function completeOnboarding(User $user): void
    {
        UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            ['height_cm' => 170, 'current_weight_kg' => 70, 'meals_per_day' => 4]
        );

        $objective = Objective::firstOrCreate(
            ['code' => 'onboarding_test_obj'],
            ['name' => 'Objetivo test', 'status' => 'active']
        );
        DB::table('user_objectives')->updateOrInsert(
            ['user_id' => $user->id, 'objective_id' => $objective->id],
            ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
        );

        $group = FamilyGroup::create([
            'name' => 'Hogar test', 'owner_user_id' => $user->id, 'status' => 'active',
        ]);
        DB::table('family_group_members')->insert([
            'family_group_id' => $group->id, 'user_id' => $user->id,
            'role_in_group' => 'owner', 'status' => 'active', 'joined_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_assign_default_role_is_idempotent(): void
    {
        $user = factory(User::class)->create();

        $user->assignDefaultRole();
        $user->assignDefaultRole();

        $this->assertTrue($user->fresh()->hasRole('user'));
        $this->assertSame(1, \DB::table('user_roles')
            ->where('user_id', $user->id)
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('roles.code', 'user')->count());
    }

    public function test_default_role_grants_every_required_user_screen(): void
    {
        $user = factory(User::class)->create();
        $user->assignDefaultRole();
        $this->completeOnboarding($user);

        $this->assertTrue($user->hasRole('user'));

        $this->actingAs($user)->get('/web')->assertStatus(200);

        foreach (self::REQUIRED_SCREENS as $screen) {
            $this->actingAs($user)->get('/web/' . $screen)
                ->assertStatus(200, "El usuario comun deberia poder abrir /web/{$screen}");
        }
    }

    public function test_user_without_any_role_is_forbidden_from_web(): void
    {
        // canAccessScreen() sigue protegiendo: sin rol -> 403 (no se debilito el RBAC).
        $user = factory(User::class)->create();

        $this->actingAs($user)->get('/web')->assertStatus(403);
        $this->actingAs($user)->get('/web/planning')->assertStatus(403);
    }

    public function test_default_role_cannot_reach_admin_or_teacher_screens(): void
    {
        $user = factory(User::class)->create();
        $user->assignDefaultRole();

        $this->actingAs($user)->get('/admin-web')->assertStatus(403);
        $this->actingAs($user)->get('/admin-web/products')->assertStatus(403);

        $teacher = $this->actingAs($user)->get('/teacher-web');
        $this->assertNotSame(200, $teacher->getStatusCode(), 'Un usuario comun no deberia ver el portal docente.');
    }

    public function test_legacy_web_registration_assigns_default_role_and_web_access(): void
    {
        $response = $this->post('/register', [
            'name'                  => 'Nueva',
            'lastname'              => 'Persona',
            'username'              => 'nueva_persona_web',
            'email'                 => 'nueva.persona.web@example.test',
            'password'              => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);
        $response->assertRedirect();

        $user = User::where('email', 'nueva.persona.web@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole('user'), 'El registro web legacy debe asignar el rol por defecto.');
        $this->completeOnboarding($user);

        $this->actingAs($user)->get('/web')->assertStatus(200);
        $this->actingAs($user)->get('/web/shopping-list')->assertStatus(200);
    }

    public function test_api_registration_assigns_default_role_and_web_access(): void
    {
        $res = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Api',
            'lastname'              => 'User',
            'email'                 => 'api.user.web@example.test',
            'password'              => 'secret1234',
            'password_confirmation' => 'secret1234',
        ])->assertStatus(201);

        $user = User::findOrFail((int) $res->json('data.id'));
        $this->assertTrue($user->hasRole('user'));
        $this->completeOnboarding($user);

        $this->actingAs($user)->get('/web')->assertStatus(200);
        $this->actingAs($user)->get('/web/planning')->assertStatus(200);
    }

    public function test_demo_scenario_user_can_access_web_screens(): void
    {
        require_once database_path('seeds/DemoScenarioSeeder.php');
        $this->seed(\DemoScenarioSeeder::class);

        $laura = User::where('email', \DemoScenarioSeeder::USER_EMAIL)->firstOrFail();

        $this->assertTrue($laura->hasRole('user'));
        $this->assertTrue($laura->hasPermission('web.user.dashboard'));

        $this->actingAs($laura)->get('/web')->assertStatus(200);
        $this->actingAs($laura)->get('/web/planning')->assertStatus(200);
        $this->actingAs($laura)->get('/web/shopping-list')->assertStatus(200);
    }
}
