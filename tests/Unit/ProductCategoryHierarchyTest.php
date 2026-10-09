<?php

namespace Tests\Unit;

use App\AuditLog;
use App\Exceptions\Ingredients\IngredientException;
use App\ProductCategory;
use App\Repositories\ProductCategories\ProductCategoryRepository;
use App\Services\ProductCategories\ProductCategoryService;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Standalone database fixture: no Laravel bootstrap, .env, migrations or seeds.
 * Run with --no-configuration --bootstrap vendor/autoload.php and this file.
 * PostgreSQL ordering is instrumented, not a live concurrency test.
 */
class ProductCategoryHierarchyTest extends TestCase
{
    private $capsule;
    private $connection;
    private $service;
    private $previousFacadeApplication;
    private $previousResolver;
    private $previousDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $this->previousResolver = Model::getConnectionResolver();
        $this->previousDispatcher = Model::getEventDispatcher();
        $container = new Container;
        $this->capsule = new Capsule($container);
        $this->capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $this->capsule->getDatabaseManager()->extend('sqlite', function () {
            return new CategoryHierarchyTestConnection(new PDO('sqlite::memory:'), ':memory:', '', ['driver' => 'sqlite']);
        });
        $this->capsule->setEventDispatcher(new Dispatcher($container));
        $this->capsule->bootEloquent();
        $container->instance('db', $this->capsule->getDatabaseManager());
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        Model::clearBootedModels();
        $this->connection = $this->capsule->getConnection();
        $this->assertSame('sqlite', $this->connection->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME));
        $this->assertSame(':memory:', $this->connection->getDatabaseName());
        $this->connection->getSchemaBuilder()->create('product_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 150);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
        $this->connection->getSchemaBuilder()->create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('action');
            $table->string('entity_name');
            $table->string('entity_id');
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('ip_address');
            $table->string('user_agent');
            $table->timestamp('created_at')->nullable();
        });
        $this->service = new ProductCategoryService(new ProductCategoryRepository);
    }

    protected function tearDown(): void
    {
        $this->capsule->getDatabaseManager()->purge();
        Model::clearBootedModels();
        if ($this->previousResolver) {
            Model::setConnectionResolver($this->previousResolver);
        } else {
            Model::unsetConnectionResolver();
        }
        if ($this->previousDispatcher) {
            Model::setEventDispatcher($this->previousDispatcher);
        } else {
            Model::unsetEventDispatcher();
        }
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacadeApplication);
        parent::tearDown();
    }

    private function create(array $data)
    {
        return $this->service->create(18, $data, '127.0.0.1', 'category-hierarchy-local-test');
    }

    private function update($id, array $data)
    {
        return $this->service->update(18, $id, $data, '127.0.0.1', 'category-hierarchy-local-test');
    }

    private function delete($id)
    {
        return $this->service->delete(18, $id, '127.0.0.1', 'category-hierarchy-local-test');
    }

    private function restore($id)
    {
        return $this->service->restore(18, $id, '127.0.0.1', 'category-hierarchy-local-test');
    }

    private function rejects($code, $status, callable $operation)
    {
        $categories = ProductCategory::withTrashed()->orderBy('id')->get()->toArray();
        $audits = AuditLog::count();
        try {
            $operation();
            $this->fail('Expected category rejection: '.$code);
        } catch (IngredientException $exception) {
            $this->assertSame($code, $exception->getErrorCode());
            $this->assertSame($status, $exception->getHttpStatus());
        }
        $this->assertSame($categories, ProductCategory::withTrashed()->orderBy('id')->get()->toArray());
        $this->assertSame($audits, AuditLog::count());
        $this->assertSame(0, $this->connection->transactionLevel());
    }

    public function test_same_name_in_different_branches_and_root_is_allowed(): void
    {
        $food = $this->create(['name' => 'Alimentos']);
        $cleaning = $this->create(['name' => 'Limpieza']);
        $a = $this->create(['name' => 'Otros', 'parent_id' => $food->id]);
        $b = $this->create(['name' => 'otros', 'parent_id' => $cleaning->id]);
        $root = $this->create(['name' => 'OTROS']);
        $this->assertNotEquals($a->id, $b->id);
        $this->assertNull($root->parent_id);
        $this->assertEquals($food->id, $a->parent_id);
        $this->assertEquals($cleaning->id, $b->parent_id);
        $this->assertSame(5, AuditLog::count());
    }

    public function test_normalized_siblings_and_roots_conflict_without_writing(): void
    {
        $parent = $this->create(['name' => 'Alimentos']);
        $this->create(['name' => '  Arroz   integral ', 'parent_id' => $parent->id]);
        $this->rejects('PRODUCT_CATEGORY_NAME_ALREADY_EXISTS', 409, function () use ($parent) {
            $this->create(['name' => 'arroz integral', 'parent_id' => $parent->id]);
        });
        $this->rejects('PRODUCT_CATEGORY_NAME_ALREADY_EXISTS', 409, function () {
            $this->create(['name' => ' ALIMENTOS ', 'parent_id' => null]);
        });
    }

    public function test_unicode_case_and_legacy_whitespace_are_normalized_without_folding_accents(): void
    {
        ProductCategory::create(['name' => 'LÁCTEOS   enteros', 'status' => 'active']);
        $this->rejects('PRODUCT_CATEGORY_NAME_ALREADY_EXISTS', 409, function () {
            $this->create(['name' => 'lácteos enteros']);
        });
        $unaccented = $this->create(['name' => 'Lacteos enteros']);
        $this->assertSame('Lacteos enteros', $unaccented->name);
    }

    public function test_parent_only_move_checks_destination_and_can_move_to_free_root(): void
    {
        $one = $this->create(['name' => 'Uno']);
        $two = $this->create(['name' => 'Dos']);
        $this->create(['name' => 'Otros', 'parent_id' => $one->id]);
        $moving = ProductCategory::create(['name' => 'Otros', 'parent_id' => $two->id, 'status' => 'active']);
        $this->rejects('PRODUCT_CATEGORY_NAME_ALREADY_EXISTS', 409, function () use ($moving, $one) {
            $this->update($moving->id, ['parent_id' => $one->id]);
        });
        $this->assertNull($this->update($moving->id, ['parent_id' => null])->parent_id);
    }

    public function test_parent_only_move_to_existing_root_is_rejected(): void
    {
        $parent = $this->create(['name' => 'Alimentos']);
        $this->create(['name' => 'Otros']);
        $moving = ProductCategory::create(['name' => 'Otros', 'parent_id' => $parent->id, 'status' => 'active']);
        $this->rejects('PRODUCT_CATEGORY_NAME_ALREADY_EXISTS', 409, function () use ($moving) {
            $this->update($moving->id, ['parent_id' => null]);
        });
    }

    public function test_inactive_duplicates_are_allowed_but_status_only_activation_conflicts(): void
    {
        $this->create(['name' => 'Otros']);
        $inactive = $this->create(['name' => 'Otros', 'status' => 'inactive']);
        $this->rejects('PRODUCT_CATEGORY_NAME_ALREADY_EXISTS', 409, function () use ($inactive) {
            $this->update($inactive->id, ['status' => 'active']);
        });
    }

    public function test_status_only_activation_revalidates_parent(): void
    {
        $parent = $this->create(['name' => 'Alimentos']);
        $child = $this->create(['name' => 'Otros', 'parent_id' => $parent->id, 'status' => 'inactive']);
        $this->update($parent->id, ['status' => 'inactive']);
        $this->rejects('PRODUCT_CATEGORY_PARENT_INACTIVE', 422, function () use ($child) {
            $this->update($child->id, ['status' => 'active']);
        });
    }

    public function test_final_name_parent_and_status_are_checked_together(): void
    {
        $parent = $this->create(['name' => 'Alimentos']);
        $this->create(['name' => 'Otros']);
        $moving = $this->create(['name' => 'Provisional', 'status' => 'inactive']);
        $saved = $this->update($moving->id, ['name' => ' otros ', 'parent_id' => $parent->id, 'status' => 'active']);
        $this->assertSame('otros', $saved->name);
        $this->assertSame('active', $saved->status);
        $this->assertEquals($parent->id, $saved->parent_id);
        $other = $this->create(['name' => 'Otra provisional', 'status' => 'inactive']);
        $this->rejects('PRODUCT_CATEGORY_NAME_ALREADY_EXISTS', 409, function () use ($other, $parent) {
            $this->update($other->id, ['name' => 'Otros', 'parent_id' => $parent->id, 'status' => 'active']);
        });
    }

    public function test_renaming_inactive_and_deactivating_with_a_duplicate_name_is_allowed(): void
    {
        $this->create(['name' => 'Otros']);
        $row = $this->create(['name' => 'Temporal']);
        $saved = $this->update($row->id, ['name' => 'Otros', 'status' => 'inactive']);
        $this->assertSame('inactive', $saved->status);
        $this->assertSame('Otros', $this->update($row->id, ['name' => 'Otros'])->name);
    }

    public function test_restore_checks_siblings_and_preserves_original_ids(): void
    {
        $one = $this->create(['name' => 'Uno']);
        $two = $this->create(['name' => 'Dos']);
        $deleted = $this->create(['name' => 'Otros', 'parent_id' => $one->id]);
        $this->delete($deleted->id);
        $this->create(['name' => 'Otros', 'parent_id' => $two->id]);
        $restored = $this->restore($deleted->id);
        $this->assertEquals($deleted->id, $restored->id);
        $this->assertSame('active', $restored->status);
        $this->assertNull($restored->deleted_at);
        $this->delete($restored->id);
        $this->create(['name' => 'Otros', 'parent_id' => $one->id]);
        $this->rejects('PRODUCT_CATEGORY_NAME_ALREADY_EXISTS', 409, function () use ($restored) {
            $this->restore($restored->id);
        });
    }

    public function test_restore_rejects_inactive_deleted_or_missing_parent(): void
    {
        $parent = $this->create(['name' => 'Padre']);
        $child = $this->create(['name' => 'Hijo', 'parent_id' => $parent->id]);
        $this->delete($child->id);
        $this->update($parent->id, ['status' => 'inactive']);
        $this->rejects('PRODUCT_CATEGORY_PARENT_INACTIVE', 422, function () use ($child) { $this->restore($child->id); });
        $this->delete($parent->id);
        $this->rejects('PRODUCT_CATEGORY_PARENT_INACTIVE', 422, function () use ($child) { $this->restore($child->id); });
        ProductCategory::withTrashed()->where('id', $parent->id)->forceDelete();
        $this->rejects('PRODUCT_CATEGORY_PARENT_NOT_FOUND', 422, function () use ($child) { $this->restore($child->id); });
    }

    public function test_restore_root_conflicts_until_its_active_namesake_is_deactivated(): void
    {
        $deleted = $this->create(['name' => 'Otros']);
        $this->delete($deleted->id);
        $replacement = $this->create(['name' => 'OTROS']);
        $this->rejects('PRODUCT_CATEGORY_NAME_ALREADY_EXISTS', 409, function () use ($deleted) { $this->restore($deleted->id); });
        $this->update($replacement->id, ['status' => 'inactive']);
        $restored = $this->restore($deleted->id);
        $this->assertEquals($deleted->id, $restored->id);
        $this->assertNull($restored->parent_id);
        $this->rejects('PRODUCT_CATEGORY_NAME_ALREADY_EXISTS', 409, function () use ($replacement) {
            $this->update($replacement->id, ['status' => 'active']);
        });
    }

    public function test_self_parent_and_descendant_move_are_rejected(): void
    {
        $root = $this->create(['name' => 'Raíz']);
        $child = $this->create(['name' => 'Hijo', 'parent_id' => $root->id]);
        $grandchild = $this->create(['name' => 'Nieto', 'parent_id' => $child->id]);
        $this->rejects('PRODUCT_CATEGORY_SELF_PARENT_FORBIDDEN', 422, function () use ($root) {
            $this->update($root->id, ['parent_id' => $root->id]);
        });
        $this->rejects('PRODUCT_CATEGORY_CYCLE_FORBIDDEN', 422, function () use ($root, $grandchild) {
            $this->update($root->id, ['parent_id' => $grandchild->id]);
        });
    }

    public function test_restore_cannot_complete_a_cycle_hidden_by_soft_delete(): void
    {
        $root = $this->create(['name' => 'Raíz']);
        $child = $this->create(['name' => 'Hijo', 'parent_id' => $root->id]);
        $this->delete($root->id);
        ProductCategory::withTrashed()->where('id', $root->id)->update(['parent_id' => $child->id]);
        $this->rejects('PRODUCT_CATEGORY_CYCLE_FORBIDDEN', 422, function () use ($root) { $this->restore($root->id); });
    }

    public function test_existing_cycle_in_ancestors_rejects_new_child_without_looping(): void
    {
        $one = $this->create(['name' => 'Uno']);
        $two = $this->create(['name' => 'Dos', 'parent_id' => $one->id]);
        ProductCategory::where('id', $one->id)->update(['parent_id' => $two->id]);
        $this->rejects('PRODUCT_CATEGORY_CYCLE_FORBIDDEN', 422, function () use ($two) {
            $this->create(['name' => 'Nuevo', 'parent_id' => $two->id]);
        });
    }

    public function test_failed_audit_rolls_back_the_category_mutation(): void
    {
        $category = $this->create(['name' => 'Antes']);
        $before = $category->toArray();
        AuditLog::creating(function () { throw new \RuntimeException('Simulated audit storage failure'); });
        try {
            $this->update($category->id, ['name' => 'Después']);
            $this->fail('Expected simulated audit failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated audit storage failure', $exception->getMessage());
        }
        $this->assertSame($before, $category->fresh('parent')->toArray());
        $this->assertSame(1, AuditLog::count());
        $this->assertSame(0, $this->connection->transactionLevel());
    }

    public function test_unchanged_update_does_not_duplicate_audit_and_success_records_parent(): void
    {
        $root = $this->create(['name' => 'Raíz']);
        $child = $this->create(['name' => 'Hijo']);
        $this->update($child->id, ['parent_id' => $root->id]);
        $audit = AuditLog::orderByDesc('id')->first();
        $this->assertSame('product-category.updated', $audit->action);
        $this->assertEquals(18, $audit->user_id);
        $this->assertNull($audit->old_values['parent_id']);
        $this->assertEquals($root->id, $audit->new_values['parent_id']);
        $count = AuditLog::count();
        $this->update($child->id, ['name' => 'Hijo', 'parent_id' => $root->id]);
        $this->assertSame($count, AuditLog::count());
    }

    public function test_postgresql_lock_precedes_category_reads_for_every_mutation(): void
    {
        $this->connection->emulatePostgres = true;
        $operations = [
            function () { return $this->create(['name' => 'Raíz'])->id; },
            function ($id) { $this->update($id, ['name' => 'Raíz editada']); return $id; },
            function ($id) { $this->delete($id); return $id; },
            function ($id) { $this->restore($id); return $id; },
        ];
        $id = null;
        $keys = [];
        foreach ($operations as $operation) {
            $this->connection->eventsSeen = [];
            $id = $operation($id);
            $events = $this->connection->eventsSeen;
            $this->assertNotEmpty($events);
            $this->assertSame('lock', $events[0]['kind']);
            $this->assertSame(1, $events[0]['level']);
            $keys[] = $events[0]['bindings'];
            $this->assertSame(0, $this->connection->transactionLevel());
        }
        $this->assertSame($keys[0], $keys[1]);
        $this->assertSame($keys[0], $keys[2]);
        $this->assertSame($keys[0], $keys[3]);
        $this->assertSame([1128484931], $keys[0], 'Shared one-argument lock contract with the additive importer.');
        $this->assertSame(4, AuditLog::count());
    }
}

class CategoryHierarchyTestConnection extends SQLiteConnection
{
    public $emulatePostgres = false;
    public $eventsSeen = [];

    public function getDriverName()
    {
        return $this->emulatePostgres ? 'pgsql' : parent::getDriverName();
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        if ($this->emulatePostgres && strpos($query, 'pg_advisory_xact_lock') !== false) {
            $this->eventsSeen[] = ['kind' => 'lock', 'level' => $this->transactionLevel(), 'bindings' => $bindings];
            return [];
        }
        if ($this->emulatePostgres && strpos($query, 'product_categories') !== false) {
            $this->eventsSeen[] = ['kind' => 'read', 'level' => $this->transactionLevel()];
        }
        return parent::select($query, $bindings, $useReadPdo);
    }
}
