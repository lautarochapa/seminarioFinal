<?php

namespace Tests\Feature\Api\V1\StockExpiration;

use App\AuditLog;
use App\Brand;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Notification;
use App\Product;
use App\StockAlert;
use App\StockItem;
use App\StockLocation;
use App\StockMovement;
use App\StockWasteLog;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessExpiredStockTest extends TestCase
{
    use RefreshDatabase;

    private function groupWithOwner()
    {
        $user = factory(User::class)->create();
        $group = factory(FamilyGroup::class)->create([
            'owner_user_id' => $user->id,
            'status' => 'active',
        ]);

        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id' => $user->id,
            'role_in_group' => 'owner',
            'status' => 'active',
        ]);

        return [$user, $group];
    }

    private function unit()
    {
        return UnitMeasure::create([
            'code' => 'exp_'.uniqid(),
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
            'codigo' => 'EXP'.uniqid(),
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

    private function stockItem(FamilyGroup $group, array $overrides = [])
    {
        return StockItem::create(array_merge([
            'family_group_id' => $group->id,
            'product_id' => $this->product()->id,
            'stock_location_id' => $this->location($group)->id,
            'quantity' => 3,
            'unit_id' => $this->unit()->id,
            'expiration_date' => now()->subDay()->toDateString(),
            'estimated_purchase_price' => 12.50,
            'status' => 'active',
        ], $overrides));
    }

    public function test_non_expired_stock_is_not_processed()
    {
        list($owner, $group) = $this->groupWithOwner();
        $item = $this->stockItem($group, ['expiration_date' => now()->addDay()->toDateString()]);

        $this->artisan('stock:process-expired')->assertExitCode(0);

        $item = $item->fresh();
        $this->assertEquals('active', $item->status);
        $this->assertEquals('3.0000', $item->quantity);
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, StockWasteLog::count());
        $this->assertSame(0, Notification::where('user_id', $owner->id)->count());
    }

    public function test_zero_quantity_expired_stock_is_not_processed()
    {
        list($owner, $group) = $this->groupWithOwner();
        $item = $this->stockItem($group, ['quantity' => 0]);

        $this->artisan('stock:process-expired')->assertExitCode(0);

        $this->assertEquals('active', $item->fresh()->status);
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, StockWasteLog::count());
        $this->assertSame(0, Notification::where('user_id', $owner->id)->count());
    }

    public function test_expired_stock_is_processed_with_movement_waste_alert_notification_and_audit()
    {
        list($owner, $group) = $this->groupWithOwner();
        $item = $this->stockItem($group, ['quantity' => 4, 'estimated_purchase_price' => 10]);

        $this->artisan('stock:process-expired')->assertExitCode(0);

        $item = $item->fresh();
        $this->assertEquals('expired', $item->status);
        $this->assertEquals('0.0000', $item->quantity);

        $this->assertDatabaseHas('stock_movements', [
            'family_group_id' => $group->id,
            'stock_item_id' => $item->id,
            'movement_type' => 'expiration',
            'quantity' => '-4.0000',
        ]);
        $this->assertDatabaseHas('stock_waste_logs', [
            'family_group_id' => $group->id,
            'stock_item_id' => $item->id,
            'quantity' => '4.0000',
            'estimated_loss_amount' => '40.00',
        ]);
        $this->assertTrue(StockAlert::where('stock_item_id', $item->id)->where('alert_type', 'expired_stock_processed')->exists());
        $this->assertTrue(Notification::where('user_id', $owner->id)->where('type', 'stock_expired_processed')->exists());
        $this->assertTrue(AuditLog::where('entity_name', 'stock_items')->where('action', 'stock-item.expired-processed')->exists());
    }

    public function test_command_is_idempotent()
    {
        list($owner, $group) = $this->groupWithOwner();
        $item = $this->stockItem($group);

        $this->artisan('stock:process-expired')->assertExitCode(0);
        $this->artisan('stock:process-expired')->assertExitCode(0);

        $this->assertSame(1, StockMovement::where('stock_item_id', $item->id)->where('movement_type', 'expiration')->count());
        $this->assertSame(1, StockWasteLog::where('stock_item_id', $item->id)->count());
        $this->assertSame(1, StockAlert::where('stock_item_id', $item->id)->where('alert_type', 'expired_stock_processed')->count());
        $this->assertSame(1, Notification::where('user_id', $owner->id)->where('type', 'stock_expired_processed')->count());
    }

    public function test_multiple_family_groups_are_processed_independently()
    {
        list($ownerA, $groupA) = $this->groupWithOwner();
        list($ownerB, $groupB) = $this->groupWithOwner();
        $itemA = $this->stockItem($groupA, ['quantity' => 1]);
        $itemB = $this->stockItem($groupB, ['quantity' => 2]);

        $this->artisan('stock:process-expired')->assertExitCode(0);

        $this->assertSame(1, StockMovement::where('family_group_id', $groupA->id)->where('stock_item_id', $itemA->id)->count());
        $this->assertSame(1, StockMovement::where('family_group_id', $groupB->id)->where('stock_item_id', $itemB->id)->count());
        $this->assertSame(1, Notification::where('user_id', $ownerA->id)->where('family_group_id', $groupA->id)->count());
        $this->assertSame(1, Notification::where('user_id', $ownerB->id)->where('family_group_id', $groupB->id)->count());
    }
}
