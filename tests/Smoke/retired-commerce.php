<?php

// Run only against the pre-created local admin fixture; never load the normal .env.
$qaBootstrap = dirname(__DIR__, 2).'/.runtime/qa-admin/bootstrap.php';
if (!is_file($qaBootstrap)) {
    fwrite(STDERR, "Missing isolated .runtime/qa-admin/bootstrap.php; refusing to boot.\n");
    exit(2);
}
$app = require $qaBootstrap;
$db = Illuminate\Support\Facades\DB::connection();
$expected = realpath(dirname($qaBootstrap).'/qa.sqlite');
if (config('app.env') !== 'testing' || $db->getDriverName() !== 'sqlite'
    || realpath($db->getDatabaseName()) !== $expected
    || array_keys(config('database.connections')) !== ['sqlite']) {
    throw new RuntimeException('Isolated SQLite fixture required.');
}

$checks = [];
$check = function ($condition, string $name) use (&$checks) {
    $checks[] = ['name' => $name, 'pass' => (bool) $condition];
};
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = function ($user, string $method, string $path, array $body = []) use ($app, $kernel) {
    if ($user) $app['auth']->guard()->setUser($user);
    $req = Illuminate\Http\Request::create($path, $method, [], [], [], [
        'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
    ], json_encode($body));
    $req->setUserResolver(function () use ($user) { return $user; });
    $app->instance('request', $req);
    $response = $kernel->handle($req);
    $kernel->terminate($req, $response);
    return [$response->getStatusCode(), json_decode($response->getContent(), true)];
};
$fingerprint = function () use ($db) {
    $result = [];
    foreach (['users', 'user_roles', 'permissions', 'role_permissions', 'promotions', 'payment_methods',
        'promotion_payment_methods', 'user_payment_methods', 'supermarket_product_prices', 'purchases',
        'purchase_items', 'family_groups', 'family_group_members', 'shopping_lists', 'shopping_list_items',
        'products', 'unit_measures', 'cities', 'supermarket_chains', 'supermarket_branches', 'supermarket_products', 'audit_logs'] as $table) {
        $rows = array_map(function ($row) { return json_encode($row); }, $db->table($table)->get()->all());
        sort($rows);
        $result[$table] = hash('sha256', implode("\n", $rows));
    }
    return $result;
};
$before = $fingerprint();
$db->beginTransaction();
try {
    $actors = [null];
    foreach (['catalog_admin', 'recipe_admin', 'super_admin'] as $role) {
        $actors[$role] = App\User::where('email', 'local.'.$role.'@example.invalid')->firstOrFail();
    }
    $user = App\User::create(['name' => 'Retiro local QA', 'lastname' => 'Fixture', 'username' => 'retired-commerce-qa', 'email' => 'retired-commerce@example.invalid',
        'password' => 'not-a-login', 'status' => 'active']);
    $user->roles()->sync([App\Role::where('code', 'user')->firstOrFail()->id]);
    $actors['user'] = $user;
    $routes = [
        ['GET', 'admin/payment-methods'], ['POST', 'admin/payment-methods'],
        ['GET', 'admin/payment-methods/1'], ['PATCH', 'admin/payment-methods/1'],
        ['DELETE', 'admin/payment-methods/1'], ['PATCH', 'admin/payment-methods/1/restore'],
        ['GET', 'payment-methods'], ['GET', 'users/me/payment-methods'],
        ['POST', 'users/me/payment-methods'], ['DELETE', 'users/me/payment-methods/1'],
        ['GET', 'admin/promotions'], ['POST', 'admin/promotions'], ['GET', 'admin/promotions/1'],
        ['PATCH', 'admin/promotions/1'], ['DELETE', 'admin/promotions/1'],
        ['PATCH', 'admin/promotions/1/restore'], ['GET', 'promotions'], ['GET', 'supermarket-branches/1/promotions'],
    ];
    foreach ($actors as $role => $actor) {
        foreach ($routes as [$verb, $path]) {
            [$status] = $request($actor, $verb, '/api/v1/'.$path);
            $check($status === 404, "$role $verb $path is retired (HTTP $status)");
        }
        if (!$actor) continue;
        foreach (['/admin-web/promotions', '/admin-web/payment-methods', '/web/payment-methods'] as $path) {
            [$status] = $request($actor, 'GET', $path);
            $check($status === 404, "$role $path is retired (HTTP $status)");
        }
    }
    foreach (['catalog_admin', 'super_admin'] as $role) {
        $check($actors[$role]->hasPermission('catalog.manage'), "$role keeps catalog.manage");
        $check(!$actors[$role]->hasPermission('web.admin.promotions'), "$role cannot use retired promotion permission");
        [$status] = $request($actors[$role], 'GET', '/api/v1/admin/brands');
        $check($status === 200, "$role retains catalog API (HTTP $status)");
    }
    [$status] = $request($actors['recipe_admin'], 'GET', '/api/v1/admin/brands');
    $check($status === 403, 'recipe_admin remains outside catalog.manage');

    // Existing assignments stay in the database but are not offered as usable permissions.
    $catalogRole = App\Role::where('code', 'catalog_admin')->firstOrFail();
    foreach (App\Services\Auth\RolePolicy::RETIRED_COMMERCE_PERMISSIONS as $code) {
        $permission = App\Permission::firstOrCreate(['code' => $code], [
            'module' => 'web', 'action' => 'access', 'description' => 'Historical fixture', 'status' => 'active',
        ]);
        $catalogRole->permissions()->syncWithoutDetaching([$permission->id]);
    }
    $catalogPage = app(App\Repositories\Admin\PermissionRepository::class)->paginate(['per_page' => 100]);
    $catalogCodes = $catalogPage->getCollection()->pluck('code')->all();
    $check($catalogPage->total() === App\Permission::count() - 3, 'permission catalog excludes exactly the three retired codes across pagination');
    $authData = (new App\Http\Resources\Api\V1\Auth\UserResource($actors['catalog_admin']))->resolve();
    $roleData = (new App\Http\Resources\Api\V1\Admin\RoleResource($catalogRole->load('permissions')))->resolve();
    $authCodes = $authData['permissions']->all();
    $roleCodes = collect($roleData['permissions'])->pluck('code')->all();
    foreach (App\Services\Auth\RolePolicy::RETIRED_COMMERCE_PERMISSIONS as $code) {
        $check(!in_array($code, $catalogCodes, true) && !in_array($code, $authCodes, true) && !in_array($code, $roleCodes, true), "$code hidden from permission catalog and exposed user/role assignments");
        $check($catalogRole->permissions()->where('code', $code)->exists(), "$code historical assignment preserved");
    }
    $check(in_array('catalog.manage', $authCodes, true) && in_array('catalog.manage', $roleCodes, true), 'catalog.manage remains exposed for catalog administrator');

    // Laravel 6 validation must reject any non-null association, including malformed values.
    foreach ([
        [App\Http\Requests\Api\V1\Purchases\CreatePurchaseRequest::class, 'payment_method_id'],
        [App\Http\Requests\Api\V1\Purchases\UpdatePurchaseRequest::class, 'payment_method_id'],
        [App\Http\Requests\Api\V1\SupermarketPrices\StorePriceRequest::class, 'promotion_id'],
    ] as [$requestClass, $field]) {
        $rules = (new $requestClass)->rules();
        foreach ([[], [$field => null], [$field => 1], [$field => 0], [$field => false], [$field => '1'], [$field => []]] as $index => $data) {
            $passes = Illuminate\Support\Facades\Validator::make($data, [$field => $rules[$field]])->passes();
            $check($passes === ($index < 2), "$requestClass association validation case $index");
        }
    }

    $group = App\FamilyGroup::create(['name' => 'Retiro QA', 'owner_user_id' => $user->id, 'status' => 'active']);
    App\FamilyGroupMember::create(['family_group_id' => $group->id, 'user_id' => $user->id, 'role_in_group' => 'owner', 'status' => 'active']);
    $unit = App\UnitMeasure::create(['code' => 'retired_qa_unit', 'name' => 'Unidad QA', 'type' => 'unit', 'symbol' => 'u', 'status' => 'active']);
    $product = App\Product::create(['name' => 'Producto retiro QA', 'nombre' => 'Producto retiro QA',
        'normalized_name' => 'producto retiro qa', 'brand_id' => App\Brand::firstOrFail()->id,
        'codigo' => 'RETIRED-QA', 'img' => '', 'habilitado' => 1, 'supply_id' => 0,
        'default_unit_id' => $unit->id, 'is_active' => true, 'status' => 'active']);
    $city = App\City::create(['name' => 'Ciudad QA retiro', 'province' => 'QA', 'country' => 'Argentina', 'status' => 'active']);
    $chain = App\SupermarketChain::create(['name' => 'Cadena QA retiro', 'code' => 'retired_qa', 'status' => 'active']);
    $branch = App\SupermarketBranch::create(['name' => 'Sucursal QA retiro', 'supermarket_chain_id' => $chain->id,
        'city_id' => $city->id, 'address' => 'QA', 'status' => 'active']);
    $mapping = App\SupermarketProduct::create(['product_id' => $product->id, 'supermarket_chain_id' => $chain->id,
        'supermarket_branch_id' => $branch->id, 'status' => 'active']);
    $promotion = App\Promotion::create(['name' => 'Histórica QA', 'supermarket_chain_id' => $chain->id,
        'supermarket_branch_id' => $branch->id, 'discount_type' => 'percent', 'discount_value' => 10,
        'valid_from' => now()->subDay(), 'valid_to' => now()->addDay(), 'status' => 'active']);
    $method = App\PaymentMethod::create(['name' => 'Histórico QA', 'type' => 'cash', 'status' => 'active']);
    $promotion->paymentMethods()->attach($method->id, ['created_at' => now()]);
    $userMethod = App\UserPaymentMethod::create(['user_id' => $user->id, 'payment_method_id' => $method->id, 'status' => 'active']);
    $price = App\SupermarketProductPrice::create(['supermarket_product_id' => $mapping->id, 'price' => 100,
        'currency' => 'ARS', 'scraped_at' => now(), 'status' => 'active', 'promotion_id' => $promotion->id]);
    $list = App\ShoppingList::create(['family_group_id' => $group->id, 'created_by' => $user->id, 'source_type' => 'manual', 'status' => 'draft']);
    App\ShoppingListItem::create(['shopping_list_id' => $list->id, 'product_id' => $product->id, 'quantity' => 2, 'unit_id' => $unit->id, 'status' => 'pending']);
    $comparison = app(App\Services\SupermarketComparison\SupermarketComparisonService::class);
    foreach (['percent', 'fixed'] as $type) {
        $promotion->update(['discount_type' => $type]);
        $result = $comparison->compare($user, $group->id, $list->id);
        $matched = array_values(array_filter($result['branches'], function ($b) use ($branch) { return $b['branch']['id'] === $branch->id; }))[0];
        $check($matched['total'] === 200.0 && $matched['items'][0]['total'] === 200.0, "$type historical discount cannot alter comparison total");
        $check($matched['promotions'] === [], "$type comparison returns empty compatible promotions array");
        $optimized = $comparison->optimize($user, $group->id, $list->id);
        $check($optimized['combined']['total'] === 200.0 && $optimized['cheapest_complete']['total'] === 200.0, "$type optimization uses undiscounted prices");
    }
    $historicalPrice = $price->fresh()->getAttributes();
    $historicalPromotion = $promotion->fresh()->getAttributes();
    $historicalMethod = $method->fresh()->getAttributes();
    $historicalUserMethod = $userMethod->fresh()->getAttributes();

    $pricePath = '/api/v1/admin/supermarket-products/'.$mapping->id.'/prices';
    [$status] = $request($actors['catalog_admin'], 'POST', $pricePath, ['price' => 125, 'currency' => 'ARS', 'promotion_id' => $promotion->id]);
    $check($status === 422, 'price API rejects new promotion association');
    $check($price->fresh()->getAttributes() === $historicalPrice, 'rejected price leaves previous historical row unchanged');
    try {
        app(App\Services\SupermarketProducts\SupermarketProductService::class)->addPrice($actors['catalog_admin']->id, $mapping->id,
            ['price' => 125, 'currency' => 'ARS', 'promotion_id' => $promotion->id], '127.0.0.1', 'local fixture');
        $check(false, 'price service rejects bypassed validation');
    } catch (App\Exceptions\Ingredients\IngredientException $e) {
        $check($price->fresh()->getAttributes() === $historicalPrice, 'price service blocks association before closing historical price');
    }
    [$status, $body] = $request($actors['catalog_admin'], 'POST', $pricePath, ['price' => 125, 'currency' => 'ARS', 'promotion_id' => null]);
    $check($status === 201, 'price API accepts legacy null association');
    $check($status === 201 && App\SupermarketProductPrice::findOrFail($body['data']['id'])->promotion_id === null, 'new price has no promotion');
    $check((int) $price->fresh()->promotion_id === $promotion->id, 'historical price keeps promotion FK after price rollover');

    $purchase = App\Purchase::create(['family_group_id' => $group->id, 'user_id' => $user->id,
        'purchase_date' => '2026-10-01', 'status' => 'confirmed', 'payment_method_id' => $method->id]);
    $purchasePath = '/api/v1/family-groups/'.$group->id.'/purchases';
    $purchaseBody = ['purchase_date' => '2026-10-09', 'items' => [[
        'product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => 2, 'unit_price' => 100,
    ]]];
    [$status] = $request($user, 'POST', $purchasePath, $purchaseBody + ['payment_method_id' => $method->id]);
    $check($status === 422, 'purchase API rejects new payment association');
    [$status] = $request($user, 'PATCH', $purchasePath.'/'.$purchase->id, ['payment_method_id' => $method->id]);
    $check($status === 422, 'purchase API rejects reassigning even the historic method');
    foreach ([[], ['payment_method_id' => null]] as $payload) {
        [$status] = $request($user, 'PATCH', $purchasePath.'/'.$purchase->id, $payload + ['purchase_date' => '2026-10-09']);
        $check($status === 200 && (int) $purchase->fresh()->payment_method_id === $method->id, 'purchase update accepts absent/null while preserving historical FK');
    }
    foreach ([[], ['payment_method_id' => null]] as $payload) {
        [$status, $body] = $request($user, 'POST', $purchasePath, $payload + $purchaseBody);
        $check($status === 201 && $body['data']['payment_method_id'] === null && (float) $body['data']['actual_total'] === 200.0,
            'purchase create with absent/null retains ordinary total and no method');
    }
    $service = app(App\Services\Purchases\PurchaseService::class);
    foreach (['create', 'update'] as $action) {
        try {
            if ($action === 'create') $service->create($group->id, $user->id, $purchaseBody + ['payment_method_id' => $method->id], '127.0.0.1', 'fixture');
            else $service->update($group->id, $purchase->id, $user->id, ['payment_method_id' => $method->id], '127.0.0.1', 'fixture');
            $check(false, "purchase $action service rejects bypassed validation");
        } catch (App\Exceptions\Purchases\PurchaseException $e) {
            $check((int) $purchase->fresh()->payment_method_id === $method->id, "purchase $action service rejects association without changing historic FK");
        }
    }
    $check($promotion->fresh()->getAttributes() === $historicalPromotion, 'historical promotion unchanged');
    $check($method->fresh()->getAttributes() === $historicalMethod, 'historical payment method unchanged');
    $check($userMethod->fresh()->getAttributes() === $historicalUserMethod, 'historical user payment link unchanged');
    $check($promotion->paymentMethods()->where('payment_methods.id', $method->id)->exists(), 'historical promotion payment link retained');
} catch (Throwable $error) {
    $check(false, get_class($error).': '.$error->getMessage());
} finally {
    while ($db->transactionLevel() > 0) $db->rollBack();
}
$check($before === $fingerprint(), 'all local fixture tables and role assignments unchanged after rollback');
$failed = array_values(array_filter($checks, function ($c) { return !$c['pass']; }));
echo json_encode(['result' => $failed ? 'FAIL' : 'PASS', 'database' => 'isolated qa-admin/qa.sqlite',
    'checks' => count($checks), 'failures' => $failed, 'results' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($failed ? 1 : 0);
