<?php

namespace Tests\Feature\Api\V1\CrossGroupIsolation;

use App\Budget;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Role;
use App\ShoppingList;
use App\StockItem;
use App\StockLocation;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * End-to-end isolation audit using three fixed personas (admin, userA/GroupA,
 * userB/GroupB) exercised across multiple resource types in a single suite,
 * complementing the per-module 403/404 tests that already exist elsewhere.
 */
class CrossGroupIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $userA;
    private User $userB;
    private FamilyGroup $groupA;
    private FamilyGroup $groupB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = factory(User::class)->create();
        $this->userB = factory(User::class)->create();
        $this->admin = factory(User::class)->create();

        $this->groupA = FamilyGroup::create(['name' => 'Grupo A', 'owner_user_id' => $this->userA->id, 'status' => 'active']);
        $this->groupB = FamilyGroup::create(['name' => 'Grupo B', 'owner_user_id' => $this->userB->id, 'status' => 'active']);

        FamilyGroupMember::create(['family_group_id' => $this->groupA->id, 'user_id' => $this->userA->id, 'role_in_group' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        FamilyGroupMember::create(['family_group_id' => $this->groupB->id, 'user_id' => $this->userB->id, 'role_in_group' => 'owner', 'status' => 'active', 'joined_at' => now()]);

        $role = Role::where('code', 'super_admin')->first();
        DB::table('user_roles')->insert(['user_id' => $this->admin->id, 'role_id' => $role->id, 'created_at' => now()]);
    }

    private function unit(): UnitMeasure
    {
        return UnitMeasure::create(['code' => 'u_'.uniqid(), 'name' => 'Unidad', 'type' => 'unit', 'symbol' => 'u', 'status' => 'active']);
    }

    private function product(UnitMeasure $unit): \App\Product
    {
        return \App\Product::create([
            'name' => 'Producto '.uniqid(),
            'normalized_name' => 'producto '.uniqid(),
            'brand_id' => 0,
            'default_unit_id' => $unit->id,
            'status' => 'active',
            'is_active' => true,
            'nombre' => 'Producto',
            'codigo' => 'PC'.uniqid(),
            'img' => '',
            'habilitado' => 1,
            'supply_id' => 0,
        ]);
    }

    public function test_userA_cannot_view_groupB_detail()
    {
        $this->actingAs($this->userA)
            ->getJson('/api/v1/family-groups/'.$this->groupB->id)
            ->assertStatus(403);
    }

    public function test_userA_cannot_list_groupB_members()
    {
        $this->actingAs($this->userA)
            ->getJson('/api/v1/family-groups/'.$this->groupB->id.'/members')
            ->assertStatus(403);
    }

    public function test_userA_cannot_create_stock_location_in_groupB()
    {
        $this->actingAs($this->userA)
            ->postJson('/api/v1/family-groups/'.$this->groupB->id.'/stock-locations', ['name' => 'Intruso'])
            ->assertStatus(403);
    }

    public function test_userA_cannot_view_or_modify_groupB_stock_item()
    {
        $location = StockLocation::create(['family_group_id' => $this->groupB->id, 'name' => 'Alacena B', 'type' => 'pantry', 'status' => 'active']);
        $unit = $this->unit();
        $product = $this->product($unit);
        $stockItem = StockItem::create([
            'family_group_id' => $this->groupB->id,
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'quantity' => 5,
            'status' => 'active',
        ]);

        // userA intenta acceder al stock del grupo B a través de la ruta del grupo A -> 403 (ni siquiera llega a buscar el item ajeno).
        $this->actingAs($this->userA)
            ->getJson('/api/v1/family-groups/'.$this->groupB->id.'/stock')
            ->assertStatus(403);

        $this->actingAs($this->userA)
            ->patchJson('/api/v1/family-groups/'.$this->groupB->id.'/stock/'.$stockItem->id, ['quantity' => 999])
            ->assertStatus(403);

        $this->assertDatabaseHas('stock_items', ['id' => $stockItem->id, 'quantity' => 5]);
    }

    public function test_userA_cannot_view_groupB_shopping_list()
    {
        $list = ShoppingList::create([
            'family_group_id' => $this->groupB->id,
            'created_by' => $this->userB->id,
            'source_type' => 'manual',
            'status' => 'draft',
        ]);

        $this->actingAs($this->userA)
            ->getJson('/api/v1/family-groups/'.$this->groupB->id.'/shopping-lists/'.$list->id)
            ->assertStatus(403);
    }

    public function test_userA_cannot_view_groupB_budget()
    {
        $budget = Budget::create([
            'family_group_id' => $this->groupB->id,
            'year' => 2026, 'month' => 7,
            'total_amount' => 10000, 'currency' => 'ARS', 'status' => 'active',
        ]);

        $this->actingAs($this->userA)
            ->getJson('/api/v1/family-groups/'.$this->groupB->id.'/budgets/'.$budget->id.'/summary')
            ->assertStatus(403);

        $this->actingAs($this->userA)
            ->getJson('/api/v1/family-groups/'.$this->groupB->id.'/budgets/'.$budget->id.'/projection')
            ->assertStatus(403);
    }

    public function test_userA_manual_product_request_is_not_visible_to_userB()
    {
        $unit = $this->unit();

        $response = $this->actingAs($this->userA)->postJson('/api/v1/family-groups/'.$this->groupA->id.'/stock/manual-product', [
            'product' => ['name' => 'Producto exclusivo de A', 'unit_id' => $unit->id],
            'stock' => ['quantity' => 1, 'unit_id' => $unit->id],
        ]);
        $response->assertStatus(201);
        $productId = $response->json('data.product.id');

        // Ninguno de los dos usuarios (no-admin) ve solicitudes ajenas en el listado propio.
        $listForB = $this->actingAs($this->userB)->getJson('/api/v1/product-requests')->assertStatus(200);
        $this->assertNotContains($productId, collect($listForB->json('data'))->pluck('product_id')->all());

        // El producto pendiente de A no aparece en la búsqueda de catálogo de B.
        $search = $this->actingAs($this->userB)
            ->getJson('/api/v1/products?family_group_id='.$this->groupB->id.'&search=Producto exclusivo de A')
            ->assertStatus(200);
        $this->assertEmpty($search->json('data'));
    }

    /**
     * /api/v1/family-groups/{id} uses plain membership-based scoping (findOrFailForUser)
     * with no special-case bypass for admin roles — even super_admin gets 403 on a group
     * they don't belong to via this endpoint. Cross-group visibility for admins is only
     * exposed through dedicated /admin/* endpoints (e.g. audit-logs, users), not by
     * reusing the member-facing family-group routes. This test locks in that behavior.
     */
    public function test_admin_is_not_a_member_so_also_gets_403_on_the_member_facing_endpoint()
    {
        $this->actingAs($this->admin)->getJson('/api/v1/family-groups/'.$this->groupA->id)->assertStatus(403);
        $this->actingAs($this->admin)->getJson('/api/v1/family-groups/'.$this->groupB->id)->assertStatus(403);

        $this->actingAs($this->userA)->getJson('/api/v1/family-groups/'.$this->groupA->id)->assertStatus(200);
        $this->actingAs($this->userA)->getJson('/api/v1/family-groups/'.$this->groupB->id)->assertStatus(403);
        $this->actingAs($this->userB)->getJson('/api/v1/family-groups/'.$this->groupA->id)->assertStatus(403);
    }

    public function test_userA_without_admin_permission_cannot_access_audit_logs()
    {
        $this->actingAs($this->userA)
            ->getJson('/api/v1/admin/audit-logs')
            ->assertStatus(403);
    }

    public function test_admin_can_access_audit_logs_across_groups()
    {
        $this->actingAs($this->userA)->postJson('/api/v1/family-groups/'.$this->groupA->id.'/stock-locations', ['name' => 'Despensa A'])
            ->assertStatus(201);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/audit-logs')
            ->assertStatus(200);
    }
}
