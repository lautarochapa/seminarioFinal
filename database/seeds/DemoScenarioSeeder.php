<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require_once __DIR__ . '/GlobalCatalogSeeder.php';

/**
 * DemoScenarioSeeder — escenario de demostracion del flujo principal de usuario.
 *
 * Extiende GlobalCatalogSeeder (datos de plataforma) y agrega ENCIMA los datos
 * del usuario de demostracion: Laura Demo, su grupo, perfil, presupuesto, stock
 * inicial y reglas de minimo. Todo lo que Laura tiene precargado, un usuario
 * nuevo lo crea igual mediante las APIs normales de la aplicacion.
 *
 * Se ejecuta con `php artisan demo:prepare`. Es idempotente: reejecutarlo
 * restaura el estado inicial del escenario. Fechas relativas a now().
 */
class DemoScenarioSeeder extends GlobalCatalogSeeder
{
    const USER_EMAIL   = 'laura.demo@cccontrol.test';
    const USER_PASS    = 'demo1234';
    const GROUP_NAME   = 'Familia Demo';
    const BUDGET_TOTAL = 90000.00;

    /** Alias del barcode reservado del catalogo (se mantiene por compatibilidad). */
    const UNKNOWN_BARCODE = GlobalCatalogSeeder::UNKNOWN_BARCODE;

    public function run()
    {
        // 1. Datos globales de la plataforma.
        parent::run();

        // 2. Datos del usuario de demostracion (creables por cualquier usuario via API).
        $this->say('DemoScenario: usuario Laura Demo + perfil + objetivo + restriccion...');
        $userId = $this->ensureUser();
        $this->ensureProfile($userId);

        $this->say('DemoScenario: grupo familiar + presupuesto...');
        $groupId = $this->ensureFamilyGroup($userId, $this->cityId);
        $this->ensureBudget($groupId);
        $this->ensureMealPlanPreferences($groupId);

        $this->say('DemoScenario: stock inicial + reglas de minimo + vencimientos...');
        $this->ensureStock($groupId, $this->productIds, $this->units);
        $this->ensureMinimumRules($groupId, $this->productIds, $this->units);

        $this->cleanupEmptyDemoLists($groupId);

        $this->say('DemoScenario listo. Usuario: ' . self::USER_EMAIL . ' / ' . self::USER_PASS);
    }

    // ─────────────────────────────────────────────────────────── usuario / perfil

    private function ensureUser(): int
    {
        DB::table('users')->updateOrInsert(
            ['email' => self::USER_EMAIL],
            [
                'name'              => 'Laura',
                'lastname'          => 'Demo',
                'username'          => 'laura.demo',
                'password'          => Hash::make(self::USER_PASS),
                'status'            => 'active',
                'nivel_acceso'      => '1',
                'email_verified_at' => $this->now,
                'updated_at'        => $this->now,
                'created_at'        => $this->now,
                'deleted_at'        => null,
            ]
        );
        $userId = (int) DB::table('users')->where('email', self::USER_EMAIL)->value('id');

        // Rol por defecto: el mismo que recibe cualquier usuario que se registra.
        // Habilita las pantallas de usuario final (/web/*) via el RBAC existente.
        $userRoleId = DB::table('roles')->where('code', 'user')->where('status', 'active')->value('id');
        if ($userRoleId) {
            DB::table('user_roles')->updateOrInsert(
                ['user_id' => $userId, 'role_id' => $userRoleId],
                ['created_at' => $this->now]
            );
        }

        return $userId;
    }

    private function ensureProfile(int $userId): void
    {
        DB::table('user_profiles')->updateOrInsert(
            ['user_id' => $userId],
            [
                'birth_date'                => '1991-03-12',
                'gender'                    => 'female',
                'height_cm'                 => 165,
                'current_weight_kg'         => 72.0,
                'target_weight_kg'          => 66.0,
                'activity_level'            => 'light',
                'meals_per_day'             => 4,
                'uses_app_for_health'       => true,
                'uses_app_for_budget'       => true,
                'uses_app_for_organization' => true,
                'notes'                     => 'Perfil demo del recorrido principal.',
                'updated_at'                => $this->now,
                'created_at'                => $this->now,
            ]
        );

        // Vinculos usuario -> objetivo / restriccion (los catalogos ya existen en GlobalCatalogSeeder).
        $objectiveId = (int) DB::table('objectives')->where('code', 'lose_weight')->value('id');
        DB::table('user_objectives')->updateOrInsert(
            ['user_id' => $userId, 'objective_id' => $objectiveId],
            [
                'priority'   => 1,
                'is_active'  => true,
                'notes'      => null,
                'updated_at' => $this->now,
                'created_at' => $this->now,
            ]
        );

        $restrictionId = (int) DB::table('dietary_restrictions')->where('code', 'lactose_free')->value('id');
        DB::table('user_dietary_restrictions')->updateOrInsert(
            ['user_id' => $userId, 'dietary_restriction_id' => $restrictionId],
            ['notes' => 'Preferencia declarada en la demo.', 'created_at' => $this->now]
        );
    }

    // ─────────────────────────────────────────────────────────── grupo / presupuesto

    private function ensureFamilyGroup(int $userId, int $cityId): int
    {
        $existing = DB::table('family_groups')
            ->where('owner_user_id', $userId)
            ->where('name', self::GROUP_NAME)
            ->first();

        if ($existing) {
            $groupId = (int) $existing->id;
        } else {
            $groupId = (int) DB::table('family_groups')->insertGetId([
                'name'              => self::GROUP_NAME,
                'owner_user_id'     => $userId,
                'city_id'           => $cityId,
                'default_address'   => 'Onelli 500, San Carlos de Bariloche',
                'default_latitude'  => -41.1335,
                'default_longitude' => -71.3103,
                'status'            => 'active',
                'created_at'        => $this->now,
                'updated_at'        => $this->now,
            ]);
        }

        DB::table('family_group_members')->updateOrInsert(
            ['family_group_id' => $groupId, 'user_id' => $userId],
            [
                'role_in_group' => 'owner',
                'status'        => 'active',
                'joined_at'     => $this->now,
                'created_at'    => $this->now,
                'updated_at'    => $this->now,
            ]
        );

        DB::table('family_group_preferences')->updateOrInsert(
            ['family_group_id' => $groupId],
            [
                'default_budget_mode'          => 'monthly',
                'default_shopping_mode'        => 'cost',
                'default_recipe_priority_mode' => 'balanced',
                'allow_auto_stock_discount'    => true,
                'created_at'                   => $this->now,
                'updated_at'                   => $this->now,
            ]
        );

        return $groupId;
    }

    private function ensureBudget(int $groupId): void
    {
        DB::table('budgets')->updateOrInsert(
            [
                'family_group_id' => $groupId,
                'year'            => (int) $this->now->year,
                'month'           => (int) $this->now->month,
            ],
            [
                'total_amount' => self::BUDGET_TOTAL,
                'currency'     => 'ARS',
                'status'       => 'active',
                'created_at'   => $this->now,
                'updated_at'   => $this->now,
                'deleted_at'   => null,
            ]
        );
    }

    /**
     * Elimina listas de compra residuales del grupo demo: las que NO tienen
     * ningun rastro de uso real (0 items, 0 sesiones de compra, 0 compras,
     * 0 movimientos de presupuesto), sin importar su estado. Cualquier lista con
     * el minimo contenido o actividad se conserva. `demo:prepare` es un
     * "restaurar escenario", asi que esto deja una pizarra limpia sin borrar
     * nada real. No usa IDs hardcodeados: opera sobre el grupo demo resuelto.
     */
    private function cleanupEmptyDemoLists(int $groupId): void
    {
        $emptyIds = DB::table('shopping_lists as sl')
            ->where('sl.family_group_id', $groupId)
            ->whereNull('sl.deleted_at')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('shopping_list_items')
                    ->whereColumn('shopping_list_items.shopping_list_id', 'sl.id');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('shopping_sessions')
                    ->whereColumn('shopping_sessions.shopping_list_id', 'sl.id');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('purchases')
                    ->whereColumn('purchases.shopping_list_id', 'sl.id');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('budget_movements')
                    ->whereColumn('budget_movements.related_shopping_list_id', 'sl.id');
            })
            ->pluck('sl.id');

        if ($emptyIds->isNotEmpty()) {
            DB::table('shopping_lists')->whereIn('id', $emptyIds)->delete();
            $this->say('DemoScenario: se limpiaron ' . $emptyIds->count() . ' lista(s) de compra vacia(s) del grupo demo.');
        }
    }

    private function ensureMealPlanPreferences(int $groupId): void
    {
        DB::table('meal_plan_preferences')->updateOrInsert(
            ['family_group_id' => $groupId, 'user_id' => null],
            [
                'avoid_repetition'  => true,
                'respect_budget'    => true,
                'respect_nutrition' => false,
                'respect_stock'     => false,
                'preferred_mode'    => 'balanced',
                'created_at'        => $this->now,
                'updated_at'        => $this->now,
            ]
        );
    }

    // ─────────────────────────────────────────────────────────── stock

    /**
     * Stock inicial deliberado (cantidad en la unidad BASE del ingrediente):
     *  - Panqueques cocinable YA (harina/leche/huevo/aceite cubiertos).
     *  - Revuelto de papa y huevo: "casi" (falta un poco de papa).
     *  - pollo / fideos / queso: sin stock -> apareceran en la lista.
     *  - Vencimientos: huevo +2 dias, leche +5 dias.
     */
    private function stockDefs(): array
    {
        return [
            ['harina',  1000, 'g',    'Alacena',  null],
            ['azucar',  500,  'g',    'Alacena',  null],
            ['aceite',  900,  'ml',   'Alacena',  null],
            ['leche',   1000, 'ml',   'Heladera', 5],
            ['huevo',   6,    'unit', 'Heladera', 2],
            ['arroz',   150,  'g',    'Alacena',  null],
            ['papa',    350,  'g',    'Alacena',  null],
            ['cebolla', 120,  'g',    'Heladera', 6],
            ['tomate',  100,  'g',    'Heladera', 4],
        ];
    }

    private function ensureStock(int $groupId, array $products, array $units): void
    {
        $locations = [];
        foreach (['Alacena', 'Heladera'] as $name) {
            $existing = DB::table('stock_locations')
                ->where('family_group_id', $groupId)->where('name', $name)->first();
            $locations[$name] = $existing
                ? (int) $existing->id
                : (int) DB::table('stock_locations')->insertGetId([
                    'family_group_id' => $groupId,
                    'name'            => $name,
                    'type'            => $name === 'Heladera' ? 'fridge' : 'pantry',
                    'status'          => 'active',
                    'created_at'      => $this->now,
                    'updated_at'      => $this->now,
                ]);
        }

        foreach ($this->stockDefs() as [$productKey, $qty, $unitCode, $locationName, $daysToExpire]) {
            $productId  = $products[$productKey];
            $locationId = $locations[$locationName];
            $expiry     = $daysToExpire === null ? null : $this->now->copy()->addDays($daysToExpire)->toDateString();

            DB::table('stock_items')->updateOrInsert(
                [
                    'family_group_id'   => $groupId,
                    'product_id'        => $productId,
                    'stock_location_id' => $locationId,
                ],
                [
                    'quantity'         => $qty,
                    'unit_id'          => $units[$unitCode],
                    'purchase_date'    => $this->now->copy()->subDays(3)->toDateString(),
                    'expiration_date'  => $expiry,
                    'is_open'          => false,
                    'status'           => 'active',
                    'deleted_at'       => null,
                    'updated_at'       => $this->now,
                    'created_at'       => $this->now,
                ]
            );
        }
    }

    /** Reglas de minimo por producto: arroz/tomate/leche por debajo; harina OK (contraste). */
    private function minimumRuleDefs(): array
    {
        return [
            ['arroz',  500,  'g'],
            ['tomate', 400,  'g'],
            ['leche',  2000, 'ml'],
            ['harina', 200,  'g'],
        ];
    }

    private function ensureMinimumRules(int $groupId, array $products, array $units): void
    {
        foreach ($this->minimumRuleDefs() as [$productKey, $min, $unitCode]) {
            DB::table('stock_minimum_rules')->updateOrInsert(
                [
                    'family_group_id' => $groupId,
                    'product_id'      => $products[$productKey],
                    'ingredient_id'   => null,
                ],
                [
                    'minimum_quantity' => $min,
                    'unit_id'          => $units[$unitCode],
                    'status'           => 'active',
                    'updated_at'       => $this->now,
                    'created_at'       => $this->now,
                ]
            );
        }
    }
}
