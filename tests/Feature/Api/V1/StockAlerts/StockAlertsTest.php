<?php

namespace Tests\Feature\Api\V1\StockAlerts;

use App\AuditLog;
use App\Brand;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Product;
use App\StockAlert;
use App\StockItem;
use App\StockLocation;
use App\StockMinimumRule;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAlertsTest extends TestCase
{
    use RefreshDatabase;

    private function groupWithMember($role = 'owner')
    {
        $user = factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $user->id, 'status' => 'active']);

        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id' => $user->id,
            'role_in_group' => $role,
            'status' => 'active',
        ]);

        return [$user, $group];
    }

    private function unit()
    {
        return UnitMeasure::create([
            'code' => 'alert_'.uniqid(),
            'name' => 'Unidad',
            'type' => 'unit',
            'symbol' => 'u',
            'status' => 'active',
        ]);
    }

    private function product()
    {
        $name = 'Producto '.uniqid();
        $brandName = 'Marca '.uniqid();
        $brand = Brand::create([
            'nombre' => $brandName,
            'name' => $brandName,
            'normalized_name' => strtolower($brandName),
            'status' => 'active',
            'padre' => 0,
        ]);

        return Product::create([
            'nombre' => $name,
            'brand_id' => $brand->id,
            'codigo' => 'BC'.uniqid(),
            'img' => 'product.png',
            'habilitado' => 1,
            'supply_id' => 0,
            'name' => $name,
            'normalized_name' => strtolower($name),
            'is_active' => true,
            'status' => 'active',
        ]);
    }

    private function location(FamilyGroup $group)
    {
        return StockLocation::create([
            'family_group_id' => $group->id,
            'name' => 'Alacena '.uniqid(),
            'type' => 'pantry',
            'status' => 'active',
        ]);
    }

    private function stockItem(FamilyGroup $group, array $data = [])
    {
        return StockItem::create(array_merge([
            'family_group_id' => $group->id,
            'product_id' => $this->product()->id,
            'stock_location_id' => $this->location($group)->id,
            'quantity' => 2,
            'unit_id' => $this->unit()->id,
            'expiration_date' => null,
            'status' => 'active',
        ], $data));
    }

    public function test_auth_required()
    {
        $this->getJson('/api/v1/family-groups/1/stock-alerts')->assertStatus(401);
    }

    public function test_access_to_other_group_rejected()
    {
        [$owner, $group] = $this->groupWithMember();
        [$other, $otherGroup] = $this->groupWithMember();

        $this->actingAs($owner)->getJson('/api/v1/family-groups/'.$otherGroup->id.'/stock-alerts')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FAMILY_GROUP_ACCESS_DENIED');
    }

    public function test_expiring_lists_active_items()
    {
        [$owner, $group] = $this->groupWithMember();
        $expiring = $this->stockItem($group, ['expiration_date' => now()->addDays(3)->toDateString()]);
        $this->stockItem($group, ['expiration_date' => now()->addDays(30)->toDateString()]);

        $response = $this->actingAs($owner)->getJson('/api/v1/family-groups/'.$group->id.'/stock/expiring');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $expiring->id);
    }

    public function test_low_stock_compares_rules()
    {
        [$owner, $group] = $this->groupWithMember();
        $item = $this->stockItem($group, ['quantity' => 2]);
        StockMinimumRule::create([
            'family_group_id' => $group->id,
            'product_id' => $item->product_id,
            'minimum_quantity' => 5,
            'unit_id' => $item->unit_id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($owner)->getJson('/api/v1/family-groups/'.$group->id.'/stock/low-stock');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.product_id', $item->product_id);
    }

    public function test_alerts_can_be_listed_and_marked_read()
    {
        [$owner, $group] = $this->groupWithMember();
        $item = $this->stockItem($group);
        $alert = StockAlert::create([
            'family_group_id' => $group->id,
            'stock_item_id' => $item->id,
            'product_id' => $item->product_id,
            'alert_type' => 'low_stock',
            'message' => 'Stock bajo',
            'severity' => 'medium',
            'status' => 'open',
        ]);

        $this->actingAs($owner)->getJson('/api/v1/family-groups/'.$group->id.'/stock-alerts')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $alert->id);

        $this->actingAs($owner)->patchJson('/api/v1/family-groups/'.$group->id.'/stock-alerts/'.$alert->id.'/read')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'read');
    }

    public function test_rule_crud()
    {
        [$owner, $group] = $this->groupWithMember();
        $product = $this->product();
        $unit = $this->unit();

        $create = $this->actingAs($owner)->postJson('/api/v1/family-groups/'.$group->id.'/stock-minimum-rules', [
            'product_id' => $product->id,
            'minimum_quantity' => 3,
            'unit_id' => $unit->id,
        ])->assertStatus(201);

        $ruleId = $create->json('data.id');

        $this->actingAs($owner)->getJson('/api/v1/family-groups/'.$group->id.'/stock-minimum-rules/'.$ruleId)
            ->assertStatus(200)
            ->assertJsonPath('data.product_id', $product->id);

        $this->actingAs($owner)->patchJson('/api/v1/family-groups/'.$group->id.'/stock-minimum-rules/'.$ruleId, [
            'minimum_quantity' => 4,
        ])->assertStatus(200)
            ->assertJsonPath('data.minimum_quantity', '4.0000');

        $this->actingAs($owner)->deleteJson('/api/v1/family-groups/'.$group->id.'/stock-minimum-rules/'.$ruleId)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_rule_duplicate_rejected()
    {
        [$owner, $group] = $this->groupWithMember();
        $product = $this->product();
        $unit = $this->unit();

        StockMinimumRule::create([
            'family_group_id' => $group->id,
            'product_id' => $product->id,
            'minimum_quantity' => 2,
            'unit_id' => $unit->id,
            'status' => 'active',
        ]);

        $this->actingAs($owner)->postJson('/api/v1/family-groups/'.$group->id.'/stock-minimum-rules', [
            'product_id' => $product->id,
            'minimum_quantity' => 3,
            'unit_id' => $unit->id,
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'STOCK_MINIMUM_RULE_ALREADY_EXISTS');
    }

    public function test_member_cannot_write_rules()
    {
        [$owner, $group] = $this->groupWithMember();
        $member = factory(User::class)->create();
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id' => $member->id,
            'role_in_group' => 'member',
            'status' => 'active',
        ]);

        $this->actingAs($member)->postJson('/api/v1/family-groups/'.$group->id.'/stock-minimum-rules', [
            'product_id' => $this->product()->id,
            'minimum_quantity' => 3,
            'unit_id' => $this->unit()->id,
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'FAMILY_GROUP_ACCESS_DENIED');
    }

    public function test_invalid_quantity_rejected()
    {
        [$owner, $group] = $this->groupWithMember();

        $this->actingAs($owner)->postJson('/api/v1/family-groups/'.$group->id.'/stock-minimum-rules', [
            'product_id' => $this->product()->id,
            'minimum_quantity' => -1,
            'unit_id' => $this->unit()->id,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_writes_are_audited()
    {
        [$owner, $group] = $this->groupWithMember();

        $this->actingAs($owner)->postJson('/api/v1/family-groups/'.$group->id.'/stock-minimum-rules', [
            'product_id' => $this->product()->id,
            'minimum_quantity' => 3,
            'unit_id' => $this->unit()->id,
        ])->assertStatus(201);

        $this->assertTrue(AuditLog::where('entity_name', 'stock_minimum_rules')->where('action', 'stock-minimum-rule.created')->exists());
    }
}
