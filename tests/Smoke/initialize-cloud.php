<?php

// Runs against a newly created disposable local database, never the app database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

function checkCloudSetup($condition, $message)
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$db = config('database.connections.pgsql');
checkCloudSetup(in_array($db['host'], ['localhost', '127.0.0.1'], true) && empty($db['url']), 'Solo se permite PostgreSQL local sin DATABASE_URL.');
$admin = new PDO(
    'pgsql:host='.$db['host'].';port='.$db['port'].';dbname=postgres',
    $db['username'],
    $db['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$database = 'cccontrol_cloud_smoke_'.bin2hex(random_bytes(6));
$admin->exec('CREATE DATABASE "'.$database.'"');
$exitCode = 0;

try {
    config([
        'database.default' => 'pgsql',
        'database.connections.pgsql.database' => $database,
        'database.connections.pgsql.url' => null,
        'session.driver' => 'array',
        'cache.default' => 'array',
    ]);
    Illuminate\Support\Facades\DB::purge('pgsql');
    $app['env'] = 'production';

    checkCloudSetup($kernel->call('app:initialize-database', ['--plan' => true]) === 0, 'Fallo plan.');
    checkCloudSetup(! Illuminate\Support\Facades\Schema::hasTable('migrations'), 'Plan no debe crear tablas.');
    checkCloudSetup($kernel->call('app:initialize-database') === 1, 'Produccion exige --force.');
    checkCloudSetup(! Illuminate\Support\Facades\Schema::hasTable('migrations'), 'Rechazo no debe crear tablas.');

    $options = ['--force' => true, '--seed-catalogs' => true];
    checkCloudSetup($kernel->call('app:initialize-database', $options) === 0, 'Fallo inicializacion.');
    checkCloudSetup(Illuminate\Support\Facades\DB::table('users')->count() === 0, 'No crear usuarios demo online.');
    checkCloudSetup(Illuminate\Support\Facades\DB::table('roles')->count() === 8, 'Faltan roles.');
    checkCloudSetup(Illuminate\Support\Facades\DB::table('permissions')->where('code', 'web.user.onboarding')->exists(), 'Falta permiso de onboarding.');
    foreach (['objectives', 'unit_measures', 'ingredient_categories', 'scraping_sources'] as $table) {
        checkCloudSetup(Illuminate\Support\Facades\DB::table($table)->count() > 0, 'Catalogo vacio: '.$table);
    }

    $before = [];
    foreach (['migrations', 'roles', 'permissions', 'role_permissions', 'ingredient_categories'] as $table) {
        $before[$table] = Illuminate\Support\Facades\DB::table($table)->count();
    }
    checkCloudSetup($kernel->call('app:initialize-database', $options) === 0, 'Fallo segunda ejecucion.');
    foreach ($before as $table => $count) {
        checkCloudSetup(Illuminate\Support\Facades\DB::table($table)->count() === $count, 'Duplicados en '.$table);
    }

    // Exercise the modern schema without relying on legacy recipe migrations.
    Illuminate\Support\Facades\DB::beginTransaction();
    try {
        $user = factory(App\User::class)->create();
        $group = App\FamilyGroup::create(['name' => 'Hogar prueba', 'owner_user_id' => $user->id, 'status' => 'active']);
        App\FamilyGroupMember::create([
            'family_group_id' => $group->id, 'user_id' => $user->id,
            'role_in_group' => 'owner', 'status' => 'active',
        ]);
        $summary = app(App\Services\UserHome\UserHomeSummaryService::class)->summary($user, $group->id);
        checkCloudSetup($summary['recipes']['available'] === 0, 'El dashboard de un hogar nuevo debe cargar.');
        $recipe = app(App\Services\Recipes\RecipeService::class)->create($user, [
            'name' => 'Receta de prueba', 'servings' => 2,
        ], '127.0.0.1', 'Cloud smoke');
        checkCloudSetup($recipe->fresh()->created_at !== null, 'Falta fecha de creacion de recetas.');
        $recipe->update(['name' => 'Receta actualizada']);
        checkCloudSetup($recipe->fresh()->updated_at !== null, 'Falta fecha de actualizacion de recetas.');
        $timestamps = $recipe->fresh()->only(['created_at', 'updated_at']);
        (new EnsureRecipeTimestamps())->up();
        checkCloudSetup($recipe->fresh()->only(['created_at', 'updated_at']) == $timestamps, 'La correccion no debe alterar fechas existentes.');
        $unitId = Illuminate\Support\Facades\DB::table('unit_measures')->where('code', 'g')->value('id');
        $manual = app(App\Services\ManualProductStock\ManualProductStockService::class)->create($group->id, $user, [
            'product' => ['name' => 'Arroz prueba', 'unit_id' => $unitId],
            'stock' => ['quantity' => 500, 'unit_id' => $unitId],
        ], '127.0.0.1', 'Cloud smoke');
        checkCloudSetup($manual['status'] === 201, 'No se pudo cargar producto manual sin marca.');
        checkCloudSetup($manual['product']->brand_id === null, 'Una marca desconocida debe ser nula, no un ID inexistente.');
        checkCloudSetup((float) $manual['stock_item']->quantity === 500.0, 'Cantidad de stock incorrecta.');

        $list = App\ShoppingList::create([
            'family_group_id' => $group->id, 'created_by' => $user->id,
            'source_type' => 'manual', 'status' => 'in_progress',
        ]);
        $item = App\ShoppingListItem::create([
            'shopping_list_id' => $list->id, 'free_text_name' => 'Tomate prueba',
            'unit_id' => $unitId, 'quantity' => 2, 'estimated_price' => 1800,
            'actual_price' => 1600, 'status' => 'purchased',
        ]);
        $completion = app(App\Services\ShoppingListCompletion\ShoppingListCompletionService::class);
        $payload = ['items' => [[
            'shopping_list_item_id' => $item->id, 'add_to_stock' => true,
            'create_pending_product' => true,
        ]]];
        $result = $completion->complete($user, $group->id, $list->id, $payload, '127.0.0.1', 'Cloud smoke');
        checkCloudSetup($result['summary']['items_added_to_stock_count'] === 1, 'La compra debe agregar stock.');
        checkCloudSetup((float) $result['purchase']->actual_total === 3200.0, 'Total de compra incorrecto.');
        $purchasedStock = App\StockItem::findOrFail($result['purchase']->items->first()->created_stock_item_id);
        checkCloudSetup((float) $purchasedStock->estimated_purchase_price === 1600.0, 'Se perdio el precio al crear stock.');
        $resource = new App\Http\Resources\Api\V1\ShoppingLists\ShoppingListResource($result['list']);
        $data = json_decode($resource->toJson(), true);
        checkCloudSetup((float) $data['items'][0]['actual_price'] === 1600.0, 'El detalle de lista perdio el precio.');
        checkCloudSetup($data['items'][0]['stock_processing_state'] === 'processed', 'El detalle muestra pendientes ya procesados.');
        try {
            $completion->complete($user, $group->id, $list->id, $payload, '127.0.0.1', 'Cloud smoke');
            throw new RuntimeException('Se permitio confirmar dos veces la misma compra.');
        } catch (App\Exceptions\Purchases\PurchaseException $expected) {
            checkCloudSetup((float) $purchasedStock->fresh()->quantity === 2.0, 'La compra duplico stock.');
            checkCloudSetup(App\Purchase::where('shopping_list_id', $list->id)->count() === 1, 'La compra se duplico.');
        }
        $budget = app(App\Services\Budgets\BudgetService::class)->create($group->id, $user->id, [
            'year' => (int) date('Y'), 'month' => (int) date('n'), 'total_amount' => 10000, 'currency' => 'ARS',
        ], '127.0.0.1', 'Cloud smoke');
        $budgetSummary = app(App\Services\Budgets\BudgetSummaryService::class)->summary($group->id, $budget->id, $user->id);
        checkCloudSetup($budgetSummary['spent_amount'] === 3200.0, 'La compra no impacta en el presupuesto.');
        checkCloudSetup($budgetSummary['available_amount'] === 6800.0, 'Saldo de presupuesto incorrecto.');
    } finally {
        Illuminate\Support\Facades\DB::rollBack();
    }

    view()->share('errors', new Illuminate\Support\ViewErrorBag());
    $login = view('auth.login')->render();
    checkCloudSetup(strpos($login, 'data-demo-password') === false, 'Login publico expone credenciales demo.');
    checkCloudSetup(strpos($login, 'superadmin@cccontrol.test') === false, 'Login publico expone usuario admin demo.');

    $app['env'] = 'local';
    require_once database_path('migrations/2026_06_15_000018_seed_demo_role_users.php');
    (new SeedDemoRoleUsers())->up();
    checkCloudSetup(Illuminate\Support\Facades\DB::table('users')->count() === 8, 'Se perdieron usuarios demo locales.');
    checkCloudSetup(strpos(view('auth.login')->render(), 'data-demo-password') !== false, 'Login local perdio atajos demo.');

    $passwords = Illuminate\Support\Facades\DB::table('users')->orderBy('id')->pluck('password')->all();
    $app['env'] = 'production';
    (new SeedDemoRoleUsers())->up();
    checkCloudSetup(Illuminate\Support\Facades\DB::table('users')->orderBy('id')->pluck('password')->all() === $passwords, 'No resetear cuentas existentes en produccion.');
    echo 'OK: inicializacion, catalogos, permisos, dashboard, recetas, stock, compra sin duplicados, presupuesto y aislamiento demo.'.PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    $exitCode = 1;
} finally {
    Illuminate\Support\Facades\DB::purge('pgsql');
    $admin->exec('DROP DATABASE "'.$database.'"');
}
exit($exitCode);
