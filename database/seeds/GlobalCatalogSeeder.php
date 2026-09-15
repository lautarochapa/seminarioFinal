<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * GlobalCatalogSeeder — datos GLOBALES de la plataforma.
 *
 * Siembra solo lo que pertenece al sistema y es compartido por todos los usuarios:
 * unidades, conversiones, nutrientes, tipos de comida, ciudad, objetivos y
 * restricciones disponibles, ingredientes, productos de catalogo (con codigo de
 * barras), recetas publicas, supermercados, sucursales, precios y promociones.
 *
 * NO crea usuarios, grupos, perfiles, presupuestos, stock ni planificaciones:
 * eso lo hace cada usuario mediante las APIs normales de la aplicacion.
 *
 * Es idempotente y autosuficiente. `DemoScenarioSeeder` lo extiende para agregar
 * encima los datos del usuario de demostracion (Laura Demo).
 */
class GlobalCatalogSeeder extends Seeder
{
    /** Barcode reservado que NO existe en catalogo (flujo producto desconocido). */
    const UNKNOWN_BARCODE = '7791111999999';

    /** @var \Illuminate\Support\Carbon */
    protected $now;
    protected $units = [];
    protected $nutrients = [];
    protected $ingredientIds = [];
    protected $productIds = [];
    protected $cityId;

    public function run()
    {
        $this->now = now();

        $this->say('GlobalCatalog: unidades, conversiones, nutrientes, tipos de comida, ciudad...');
        $this->units     = $this->ensureUnits();
        $this->ensureUnitConversions($this->units);
        $this->nutrients = $this->ensureNutrients($this->units);
        $this->ensureMealTypes();
        $this->cityId = $this->ensureCity();
        $this->ensureObjectiveCatalog();
        $this->ensureRestrictionCatalog();

        $this->say('GlobalCatalog: ingredientes, productos de catalogo y recetas publicas...');
        $this->ingredientIds = $this->ensureIngredients($this->units, $this->nutrients);
        $this->productIds    = $this->ensureProducts($this->units, $this->ingredientIds);
        $this->ensureRecipes($this->ingredientIds, $this->units);

        $this->say('GlobalCatalog: supermercados, sucursales, precios y promocion...');
        $this->ensureSupermarkets($this->cityId, $this->productIds);
    }

    protected function say(string $message): void
    {
        if ($this->command) {
            $this->command->info($message);
        }
    }

    // ─────────────────────────────────────────────────────────── catalogo base

    private function ensureUnits(): array
    {
        $defs = [
            ['code' => 'g',    'name' => 'Gramo',      'type' => 'weight', 'symbol' => 'g'],
            ['code' => 'kg',   'name' => 'Kilogramo',  'type' => 'weight', 'symbol' => 'kg'],
            ['code' => 'ml',   'name' => 'Mililitro',  'type' => 'volume', 'symbol' => 'ml'],
            ['code' => 'l',    'name' => 'Litro',      'type' => 'volume', 'symbol' => 'l'],
            ['code' => 'unit', 'name' => 'Unidad',     'type' => 'count',  'symbol' => 'u'],
            ['code' => 'kcal', 'name' => 'Kilocaloria','type' => 'energy', 'symbol' => 'kcal'],
            ['code' => 'mg',   'name' => 'Miligramo',  'type' => 'weight', 'symbol' => 'mg'],
        ];
        foreach ($defs as $d) {
            DB::table('unit_measures')->updateOrInsert(
                ['code' => $d['code']],
                $d + ['status' => 'active', 'created_at' => $this->now, 'updated_at' => $this->now]
            );
        }
        return DB::table('unit_measures')->pluck('id', 'code')->toArray();
    }

    private function ensureUnitConversions(array $u): void
    {
        $defs = [
            ['from' => 'kg', 'to' => 'g',  'factor' => 1000],
            ['from' => 'g',  'to' => 'kg', 'factor' => 0.001],
            ['from' => 'l',  'to' => 'ml', 'factor' => 1000],
            ['from' => 'ml', 'to' => 'l',  'factor' => 0.001],
        ];
        foreach ($defs as $c) {
            DB::table('unit_conversions')->updateOrInsert(
                [
                    'from_unit_id'  => $u[$c['from']],
                    'to_unit_id'    => $u[$c['to']],
                    'ingredient_id' => null,
                ],
                [
                    'factor'     => $c['factor'],
                    'status'     => 'active',
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]
            );
        }
    }

    private function ensureNutrients(array $u): array
    {
        $defs = [
            ['code' => 'calories',      'name' => 'Calorias',      'unit' => 'kcal'],
            ['code' => 'protein',       'name' => 'Proteinas',     'unit' => 'g'],
            ['code' => 'carbohydrates', 'name' => 'Carbohidratos', 'unit' => 'g'],
            ['code' => 'fat',           'name' => 'Grasas',        'unit' => 'g'],
            ['code' => 'sodium',        'name' => 'Sodio',         'unit' => 'mg'],
            ['code' => 'sugar',         'name' => 'Azucar',        'unit' => 'g'],
            ['code' => 'fiber',         'name' => 'Fibra',         'unit' => 'g'],
        ];
        foreach ($defs as $d) {
            DB::table('nutrients')->updateOrInsert(
                ['code' => $d['code']],
                [
                    'name'       => $d['name'],
                    'unit_id'    => $u[$d['unit']],
                    'status'     => 'active',
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]
            );
        }
        return DB::table('nutrients')->pluck('id', 'code')->toArray();
    }

    private function ensureMealTypes(): void
    {
        $types = [
            ['code' => 'breakfast', 'name' => 'Desayuno', 'sort_order' => 10],
            ['code' => 'lunch',     'name' => 'Almuerzo', 'sort_order' => 20],
            ['code' => 'snack',     'name' => 'Merienda', 'sort_order' => 30],
            ['code' => 'dinner',    'name' => 'Cena',     'sort_order' => 40],
        ];
        foreach ($types as $t) {
            DB::table('meal_types')->updateOrInsert(
                ['code' => $t['code']],
                $t + ['status' => 'active', 'created_at' => $this->now, 'updated_at' => $this->now]
            );
        }
    }

    private function ensureCity(): int
    {
        DB::table('cities')->updateOrInsert(
            ['name' => 'San Carlos de Bariloche', 'province' => 'Rio Negro', 'country' => 'Argentina'],
            [
                'latitude'   => -41.1335,
                'longitude'  => -71.3103,
                'status'     => 'active',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]
        );
        return (int) DB::table('cities')->where('name', 'San Carlos de Bariloche')->value('id');
    }

    private function ensureObjectiveCatalog(): void
    {
        $defs = [
            ['code' => 'lose_weight',     'name' => 'Bajar de peso',      'category' => 'weight'],
            ['code' => 'maintain_weight', 'name' => 'Mantener el peso',   'category' => 'weight'],
            ['code' => 'gain_muscle',     'name' => 'Ganar masa muscular','category' => 'fitness'],
            ['code' => 'eat_healthier',   'name' => 'Comer mas sano',     'category' => 'health'],
        ];
        foreach ($defs as $d) {
            DB::table('objectives')->updateOrInsert(
                ['code' => $d['code']],
                [
                    'name'       => $d['name'],
                    'category'   => $d['category'],
                    'status'     => 'active',
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]
            );
        }
    }

    private function ensureRestrictionCatalog(): void
    {
        $defs = [
            ['code' => 'lactose_free', 'name' => 'Sin lactosa'],
            ['code' => 'gluten_free',  'name' => 'Sin gluten'],
            ['code' => 'vegetarian',   'name' => 'Vegetariano'],
        ];
        foreach ($defs as $d) {
            DB::table('dietary_restrictions')->updateOrInsert(
                ['code' => $d['code']],
                ['name' => $d['name'], 'status' => 'active']
            );
        }
    }

    // ─────────────────────────────────────────────────────────── ingredientes

    /** normalized_name => [name, base unit code, nutrientes/100g] */
    private function ingredientDefs(): array
    {
        return [
            'harina 000'      => ['Harina de trigo 000', 'g',    ['calories' => 364, 'protein' => 10.3, 'carbohydrates' => 76.3, 'fat' => 1.0,  'sodium' => 2,   'sugar' => 0.3,  'fiber' => 2.7]],
            'azucar'          => ['Azucar comun',        'g',    ['calories' => 387, 'protein' => 0.0,  'carbohydrates' => 99.8, 'fat' => 0.0,  'sodium' => 1,   'sugar' => 99.8, 'fiber' => 0.0]],
            'huevo'           => ['Huevo de gallina',    'unit', ['calories' => 143, 'protein' => 12.6, 'carbohydrates' => 0.7,  'fat' => 9.5,  'sodium' => 142, 'sugar' => 0.4,  'fiber' => 0.0]],
            'leche entera'    => ['Leche entera',        'ml',   ['calories' => 61,  'protein' => 3.2,  'carbohydrates' => 4.8,  'fat' => 3.3,  'sodium' => 44,  'sugar' => 4.8,  'fiber' => 0.0]],
            'aceite girasol'  => ['Aceite de girasol',   'ml',   ['calories' => 884, 'protein' => 0.0,  'carbohydrates' => 0.0,  'fat' => 100,  'sodium' => 0,   'sugar' => 0.0,  'fiber' => 0.0]],
            'arroz blanco'    => ['Arroz blanco',        'g',    ['calories' => 130, 'protein' => 2.7,  'carbohydrates' => 28.2, 'fat' => 0.3,  'sodium' => 1,   'sugar' => 0.0,  'fiber' => 0.4]],
            'pechuga pollo'   => ['Pechuga de pollo',    'g',    ['calories' => 165, 'protein' => 31.0, 'carbohydrates' => 0.0,  'fat' => 3.6,  'sodium' => 74,  'sugar' => 0.0,  'fiber' => 0.0]],
            'cebolla'         => ['Cebolla',             'g',    ['calories' => 40,  'protein' => 1.1,  'carbohydrates' => 9.3,  'fat' => 0.1,  'sodium' => 4,   'sugar' => 4.2,  'fiber' => 1.7]],
            'tomate'          => ['Tomate fresco',       'g',    ['calories' => 18,  'protein' => 0.9,  'carbohydrates' => 3.9,  'fat' => 0.2,  'sodium' => 5,   'sugar' => 2.6,  'fiber' => 1.2]],
            'papa'            => ['Papa',                'g',    ['calories' => 77,  'protein' => 2.0,  'carbohydrates' => 17.5, 'fat' => 0.1,  'sodium' => 6,   'sugar' => 0.8,  'fiber' => 2.2]],
            'fideos secos'    => ['Fideos secos',        'g',    ['calories' => 371, 'protein' => 13.0, 'carbohydrates' => 74.7, 'fat' => 1.5,  'sodium' => 6,   'sugar' => 2.7,  'fiber' => 3.2]],
            'queso rallado'   => ['Queso rallado',       'g',    ['calories' => 431, 'protein' => 38.5, 'carbohydrates' => 3.2,  'fat' => 29.0, 'sodium' => 1600,'sugar' => 0.5,  'fiber' => 0.0]],
        ];
    }

    private function ensureIngredients(array $units, array $nutrients): array
    {
        $ids = [];
        foreach ($this->ingredientDefs() as $normalized => [$name, $unitCode, $nutrientVals]) {
            $existing = DB::table('ingredients')->where('normalized_name', $normalized)->first();
            if ($existing) {
                $ingId = (int) $existing->id;
            } else {
                $ingId = (int) DB::table('ingredients')->insertGetId([
                    'name'            => $name,
                    'normalized_name' => $normalized,
                    'base_unit_id'    => $units[$unitCode],
                    'is_generic'      => true,
                    'is_preparation'  => false,
                    'is_supplement'   => false,
                    'status'          => 'active',
                    'created_at'      => $this->now,
                    'updated_at'      => $this->now,
                ]);
            }
            $ids[$normalized] = $ingId;

            foreach ($nutrientVals as $code => $amount) {
                if (empty($nutrients[$code])) {
                    continue;
                }
                DB::table('ingredient_nutrients')->updateOrInsert(
                    ['ingredient_id' => $ingId, 'nutrient_id' => $nutrients[$code]],
                    [
                        'amount_per_100g' => $amount,
                        'source'          => 'global_catalog',
                        'status'          => 'active',
                        'created_at'      => $this->now,
                        'updated_at'      => $this->now,
                    ]
                );
            }
        }
        return $ids;
    }

    // ─────────────────────────────────────────────────────────── productos

    /**
     * key => [name, ingredient normalized_name, package unit code, net_quantity, barcode]
     *
     * Invariante clave para que la comparacion de supermercados funcione:
     * default_unit_id == package_unit_id. El precio sembrado es "por paquete".
     */
    private function productDefs(): array
    {
        return [
            'harina'  => ['Harina 000 Molino Sur 1 kg',        'harina 000',     'kg',   1.0,  '7791111000018'],
            'azucar'  => ['Azucar Refinada Dulce Sur 1 kg',    'azucar',         'kg',   1.0,  '7791111000025'],
            'huevo'   => ['Huevos Granja Feliz x6',            'huevo',          'unit', 6.0,  '7791111000032'],
            'leche'   => ['Leche Entera Vaca Blanca 1 L',      'leche entera',   'l',    1.0,  '7791111000049'],
            'aceite'  => ['Aceite de Girasol Campo Claro 900 ml','aceite girasol','l',   0.9,  '7791111000056'],
            'arroz'   => ['Arroz Largo Fino Grano de Oro 1 kg','arroz blanco',   'kg',   1.0,  '7791111000063'],
            'pollo'   => ['Pechuga de Pollo Granja Feliz 1 kg','pechuga pollo',  'kg',   1.0,  '7791111000070'],
            'cebolla' => ['Cebolla Blanca x kg',               'cebolla',        'kg',   1.0,  '7791111000087'],
            'tomate'  => ['Tomate Perita x kg',                'tomate',         'kg',   1.0,  '7791111000094'],
            'papa'    => ['Papa Blanca x kg',                  'papa',           'kg',   1.0,  '7791111000100'],
            'fideos'  => ['Fideos Tirabuzon Trigo Rico 500 g', 'fideos secos',   'kg',   0.5,  '7791111000117'],
            'queso'   => ['Queso Rallado Sabor Norte 100 g',   'queso rallado',  'kg',   0.1,  '7791111000124'],
        ];
    }

    private function ensureProducts(array $units, array $ingredientIds): array
    {
        $brandId    = $this->ensureBrand('Marca Catalogo');
        $categoryId = $this->ensureProductCategory('Almacen');

        $ids = [];
        foreach ($this->productDefs() as $key => [$name, $ingNormalized, $pkgUnitCode, $netQty, $barcode]) {
            $normalized = $this->normalize($name);
            $unitId     = (int) $units[$pkgUnitCode];
            $ingId      = (int) $ingredientIds[$ingNormalized];

            $existing = DB::table('products')->where('normalized_name', $normalized)->first();
            if ($existing) {
                $productId = (int) $existing->id;
                DB::table('products')->where('id', $productId)->update([
                    'ingredient_id'   => $ingId,
                    'default_unit_id' => $unitId,
                    'package_unit_id' => $unitId,
                    'net_quantity'    => $netQty,
                    'is_verified'     => true,
                    'is_active'       => true,
                    'status'          => 'active',
                    'updated_at'      => $this->now,
                ]);
            } else {
                $supplyId  = $this->ensureLegacySupply($name);
                $productId = (int) DB::table('products')->insertGetId([
                    'nombre'          => $name,
                    'name'            => $name,
                    'normalized_name' => $normalized,
                    'brand_id'        => $brandId,
                    'category_id'     => $categoryId,
                    'ingredient_id'   => $ingId,
                    'default_unit_id' => $unitId,
                    'package_unit_id' => $unitId,
                    'net_quantity'    => $netQty,
                    'codigo'          => $barcode,
                    'img'             => '',
                    'habilitado'      => 1,
                    'supply_id'       => $supplyId,
                    'description'     => null,
                    'is_verified'     => true,
                    'is_active'       => true,
                    'status'          => 'active',
                    'origin'          => 'catalog',
                    'created_at'      => $this->now,
                    'updated_at'      => $this->now,
                ]);
            }
            $ids[$key] = $productId;

            DB::table('product_barcodes')->updateOrInsert(
                ['barcode' => $barcode],
                [
                    'product_id' => $productId,
                    'type'       => 'EAN13',
                    'status'     => 'active',
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]
            );
        }

        // El barcode desconocido NO se inserta a proposito.
        DB::table('product_barcodes')->where('barcode', self::UNKNOWN_BARCODE)->delete();

        return $ids;
    }

    private function ensureBrand(string $name): int
    {
        $normalized = $this->normalize($name);
        $existing = DB::table('brands')->where('normalized_name', $normalized)->first();
        if ($existing) {
            return (int) $existing->id;
        }
        return (int) DB::table('brands')->insertGetId([
            'nombre'          => $name,
            'name'            => $name,
            'normalized_name' => $normalized,
            'padre'           => 0,
            'status'          => 'active',
            'created_at'      => $this->now,
            'updated_at'      => $this->now,
        ]);
    }

    private function ensureProductCategory(string $name): int
    {
        $existing = DB::table('product_categories')->where('name', $name)->first();
        if ($existing) {
            return (int) $existing->id;
        }
        return (int) DB::table('product_categories')->insertGetId([
            'name'       => $name,
            'parent_id'  => null,
            'status'     => 'active',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
    }

    private function ensureLegacyCategory(string $nombre): int
    {
        $existing = DB::table('categories')->where('nombre', $nombre)->first();
        if ($existing) {
            return (int) $existing->id;
        }
        return (int) DB::table('categories')->insertGetId([
            'nombre'     => $nombre,
            'padre'      => 0,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
    }

    private function ensureLegacySupply(string $productName): int
    {
        $nombre = substr($productName, 0, 100);
        $existing = DB::table('supplies')->where('nombre', $nombre)->first();
        if ($existing) {
            return (int) $existing->id;
        }
        return (int) DB::table('supplies')->insertGetId([
            'nombre'      => $nombre,
            'medida'      => 'u',
            'category_id' => $this->ensureLegacyCategory('Almacen'),
            'created_at'  => $this->now,
            'updated_at'  => $this->now,
        ]);
    }

    // ─────────────────────────────────────────────────────────── recetas

    /** Cada ingrediente: [ingredient normalized_name, quantity, unit code]. Unidades en base (g/ml/unit). */
    private function recipeDefs(): array
    {
        return [
            'panqueques' => [
                'name' => 'Panqueques caseros', 'servings' => 4, 'prep' => 10, 'cook' => 15, 'difficulty' => 'easy',
                'cost' => 1800.00,
                'ingredients' => [
                    ['harina 000', 200, 'g'], ['leche entera', 400, 'ml'], ['huevo', 2, 'unit'], ['aceite girasol', 20, 'ml'],
                ],
                'steps' => [
                    'Mezclar la harina con la leche y los huevos hasta obtener una masa liquida sin grumos.',
                    'Calentar una sarten con unas gotas de aceite y volcar un cucharon de masa.',
                    'Cocinar 1 minuto por lado hasta dorar. Repetir con el resto de la masa.',
                ],
            ],
            'arroz con pollo' => [
                'name' => 'Arroz con pollo', 'servings' => 4, 'prep' => 15, 'cook' => 35, 'difficulty' => 'easy',
                'cost' => 5200.00,
                'ingredients' => [
                    ['arroz blanco', 300, 'g'], ['pechuga pollo', 400, 'g'], ['cebolla', 100, 'g'], ['tomate', 150, 'g'], ['aceite girasol', 30, 'ml'],
                ],
                'steps' => [
                    'Cortar el pollo en cubos y dorar en aceite caliente.',
                    'Agregar cebolla y tomate picados y rehogar 5 minutos.',
                    'Incorporar el arroz, cubrir con agua y cocinar a fuego medio hasta absorcion.',
                ],
            ],
            'tortilla de papa' => [
                'name' => 'Tortilla de papa', 'servings' => 3, 'prep' => 20, 'cook' => 25, 'difficulty' => 'medium',
                'cost' => 2600.00,
                'ingredients' => [
                    ['papa', 500, 'g'], ['huevo', 4, 'unit'], ['cebolla', 100, 'g'], ['aceite girasol', 40, 'ml'],
                ],
                'steps' => [
                    'Pelar y cortar las papas en rodajas finas. Freir en aceite hasta que esten tiernas.',
                    'Batir los huevos y mezclar con las papas y la cebolla.',
                    'Cuajar la tortilla en sarten a fuego bajo, dando vuelta con ayuda de un plato.',
                ],
            ],
            'fideos con salsa' => [
                'name' => 'Fideos con salsa de tomate', 'servings' => 4, 'prep' => 10, 'cook' => 20, 'difficulty' => 'easy',
                'cost' => 2400.00,
                'ingredients' => [
                    ['fideos secos', 400, 'g'], ['tomate', 300, 'g'], ['cebolla', 80, 'g'], ['aceite girasol', 20, 'ml'], ['queso rallado', 40, 'g'],
                ],
                'steps' => [
                    'Rehogar la cebolla en aceite y agregar el tomate picado. Cocinar 10 minutos.',
                    'Hervir los fideos en agua con sal hasta que esten al dente.',
                    'Mezclar los fideos con la salsa y servir con queso rallado.',
                ],
            ],
            'pollo al horno con papas' => [
                'name' => 'Pollo al horno con papas', 'servings' => 4, 'prep' => 15, 'cook' => 45, 'difficulty' => 'easy',
                'cost' => 5600.00,
                'ingredients' => [
                    ['pechuga pollo', 500, 'g'], ['papa', 600, 'g'], ['cebolla', 100, 'g'], ['aceite girasol', 30, 'ml'],
                ],
                'steps' => [
                    'Cortar las papas y la cebolla en gajos y disponer en una fuente con aceite.',
                    'Apoyar la pechuga encima, salpimentar y llevar a horno medio.',
                    'Cocinar 45 minutos hasta que el pollo este dorado y las papas tiernas.',
                ],
            ],
            'bizcochuelo' => [
                'name' => 'Bizcochuelo esponjoso', 'servings' => 8, 'prep' => 20, 'cook' => 35, 'difficulty' => 'easy',
                'cost' => 2200.00,
                'ingredients' => [
                    ['harina 000', 200, 'g'], ['azucar', 200, 'g'], ['huevo', 4, 'unit'], ['leche entera', 100, 'ml'],
                ],
                'steps' => [
                    'Batir los huevos con el azucar hasta punto letra.',
                    'Incorporar la harina tamizada de forma envolvente y luego la leche tibia.',
                    'Volcar en molde enmantecado y hornear a 180 grados 35 minutos.',
                ],
            ],
            'ensalada tibia de arroz' => [
                'name' => 'Ensalada tibia de arroz', 'servings' => 2, 'prep' => 10, 'cook' => 20, 'difficulty' => 'easy',
                'cost' => 1500.00,
                'ingredients' => [
                    ['arroz blanco', 200, 'g'], ['tomate', 200, 'g'], ['cebolla', 60, 'g'], ['aceite girasol', 20, 'ml'],
                ],
                'steps' => [
                    'Hervir el arroz y escurrir.',
                    'Cortar el tomate y la cebolla en cubos chicos.',
                    'Mezclar todo tibio con un hilo de aceite y servir.',
                ],
            ],
            'revuelto de papa y huevo' => [
                'name' => 'Revuelto de papa y huevo', 'servings' => 3, 'prep' => 10, 'cook' => 15, 'difficulty' => 'easy',
                'cost' => 2100.00,
                'ingredients' => [
                    ['papa', 400, 'g'], ['huevo', 6, 'unit'], ['cebolla', 80, 'g'], ['aceite girasol', 20, 'ml'],
                ],
                'steps' => [
                    'Cortar la papa en cubos chicos y cocinar en sarten con aceite hasta dorar.',
                    'Agregar la cebolla y rehogar.',
                    'Verter los huevos batidos y revolver hasta cuajar.',
                ],
            ],
        ];
    }

    private function ensureRecipes(array $ingredientIds, array $units): array
    {
        $categoryId = $this->ensureRecipeCategory('Platos caseros');
        $ids = [];

        foreach ($this->recipeDefs() as $normalized => $def) {
            $existing = DB::table('recipes')->where('normalized_name', $normalized)->first();
            $totalMin = $def['prep'] + $def['cook'];

            if ($existing) {
                $recipeId = (int) $existing->id;
            } else {
                $recipeId = (int) DB::table('recipes')->insertGetId([
                    'nombre'            => $def['name'],
                    'name'             => $def['name'],
                    'normalized_name'  => $normalized,
                    'descripcion'      => $def['name'],
                    'description'      => $def['name'],
                    'tiempo'           => $totalMin . ' min',
                    'img'              => '',
                    'video'            => '',
                    'porcion'          => (string) $def['servings'],
                    'calorias'         => 0,
                    'servings'         => $def['servings'],
                    'prep_time_minutes'=> $def['prep'],
                    'cook_time_minutes'=> $def['cook'],
                    'difficulty'       => $def['difficulty'],
                    'source_type'      => 'official',
                    'status'           => 'active',
                    'owner_user_id'    => null,
                    'is_public'        => true,
                    'is_official'      => true,
                    'is_verified'      => true,
                    'category_id'      => $categoryId,
                    'created_at'       => $this->now,
                    'updated_at'       => $this->now,
                ]);
            }
            $ids[$normalized] = $recipeId;

            // Ingredientes (idempotente: limpiar y recrear)
            DB::table('recipe_ingredients')->where('recipe_id', $recipeId)->delete();
            foreach ($def['ingredients'] as $sort => [$ingNorm, $qty, $unitCode]) {
                DB::table('recipe_ingredients')->insert([
                    'recipe_id'     => $recipeId,
                    'ingredient_id' => $ingredientIds[$ingNorm],
                    'quantity'      => $qty,
                    'unit_id'       => $units[$unitCode],
                    'is_optional'   => false,
                    'sort_order'    => $sort,
                    'created_at'    => $this->now,
                    'updated_at'    => $this->now,
                ]);
            }

            // Pasos
            DB::table('recipe_steps')->where('recipe_id', $recipeId)->delete();
            foreach ($def['steps'] as $i => $text) {
                DB::table('recipe_steps')->insert([
                    'recipe_id'         => $recipeId,
                    'step_number'       => $i + 1,
                    'description'       => $text,
                    'estimated_minutes' => null,
                    'created_at'        => $this->now,
                    'updated_at'        => $this->now,
                ]);
            }

            $this->ensureRecipeNutrition($recipeId, $def, $ingredientIds);

            // Snapshot de costo global (family_group_id NULL) para planificacion por presupuesto
            DB::table('recipe_cost_snapshots')->where('recipe_id', $recipeId)->whereNull('family_group_id')->delete();
            DB::table('recipe_cost_snapshots')->insert([
                'recipe_id'                  => $recipeId,
                'family_group_id'            => null,
                'supermarket_chain_id'       => null,
                'supermarket_branch_id'      => null,
                'estimated_total_cost'       => $def['cost'],
                'estimated_cost_per_serving' => round($def['cost'] / max(1, $def['servings']), 2),
                'calculated_at'              => $this->now,
                'created_at'                 => $this->now,
            ]);
        }

        return $ids;
    }

    private function ensureRecipeNutrition(int $recipeId, array $def, array $ingredientIds): void
    {
        $perNutrient = ['calories' => 0.0, 'protein' => 0.0, 'carbohydrates' => 0.0, 'fat' => 0.0, 'sodium' => 0.0, 'sugar' => 0.0, 'fiber' => 0.0];

        foreach ($def['ingredients'] as [$ingNorm, $qty, $unitCode]) {
            $ingId = $ingredientIds[$ingNorm];
            $grams = $unitCode === 'unit' ? $qty * 50 : $qty;
            $rows = DB::table('ingredient_nutrients as inn')
                ->join('nutrients as n', 'n.id', '=', 'inn.nutrient_id')
                ->where('inn.ingredient_id', $ingId)
                ->pluck('inn.amount_per_100g', 'n.code');
            foreach ($perNutrient as $code => $_) {
                if (isset($rows[$code])) {
                    $perNutrient[$code] += ((float) $rows[$code]) * $grams / 100.0;
                }
            }
        }

        $servings = max(1, $def['servings']);
        DB::table('recipe_nutrition')->updateOrInsert(
            ['recipe_id' => $recipeId],
            [
                'calories_total'            => round($perNutrient['calories'], 2),
                'calories_per_serving'      => round($perNutrient['calories'] / $servings, 2),
                'protein_total'             => round($perNutrient['protein'], 2),
                'protein_per_serving'       => round($perNutrient['protein'] / $servings, 2),
                'carbohydrates_total'       => round($perNutrient['carbohydrates'], 2),
                'carbohydrates_per_serving' => round($perNutrient['carbohydrates'] / $servings, 2),
                'fat_total'                 => round($perNutrient['fat'], 2),
                'fat_per_serving'           => round($perNutrient['fat'] / $servings, 2),
                'sodium_total'              => round($perNutrient['sodium'], 2),
                'sodium_per_serving'        => round($perNutrient['sodium'] / $servings, 2),
                'sugar_total'               => round($perNutrient['sugar'], 2),
                'sugar_per_serving'         => round($perNutrient['sugar'] / $servings, 2),
                'fiber_total'               => round($perNutrient['fiber'], 2),
                'fiber_per_serving'         => round($perNutrient['fiber'] / $servings, 2),
                'calculation_status'        => 'calculated',
                'calculated_at'             => $this->now,
                'created_at'                => $this->now,
                'updated_at'                => $this->now,
            ]
        );
    }

    private function ensureRecipeCategory(string $name): int
    {
        $existing = DB::table('recipe_categories')->where('name', $name)->first();
        if ($existing) {
            return (int) $existing->id;
        }
        return (int) DB::table('recipe_categories')->insertGetId([
            'name'       => $name,
            'parent_id'  => null,
            'status'     => 'active',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
    }

    // ─────────────────────────────────────────────────────────── supermercados

    /** 3 sucursales. Precio POR PAQUETE, deterministico y distinto entre sucursales. */
    private function branchDefs(): array
    {
        return [
            'carrefour'  => ['chain_name' => 'Carrefour',  'branch' => 'Carrefour Bariloche Centro'],
            'changomas'  => ['chain_name' => 'ChangoMas',  'branch' => 'ChangoMas Bariloche'],
            'la_anonima' => ['chain_name' => 'La Anonima', 'branch' => 'La Anonima Onelli'],
        ];
    }

    /** product key => [carrefour, changomas, la_anonima] precio por paquete en ARS. */
    private function priceMatrix(): array
    {
        return [
            'harina'  => [1200, 1150, 1300],
            'azucar'  => [1400, 1500, 1350],
            'huevo'   => [2100, 2000, 2200],
            'leche'   => [1300, 1350, 1250],
            'aceite'  => [3200, 3400, 3100],
            'arroz'   => [1800, 1750, 1900],
            'pollo'   => [4200, 4500, 4000],
            'cebolla' => [900,  950,  850],
            'tomate'  => [1600, 1500, 1700],
            'papa'    => [800,  850,  780],
            'fideos'  => [950,  900,  1000],
            'queso'   => [1500, 1450, 1550],
        ];
    }

    private function ensureSupermarkets(int $cityId, array $products): void
    {
        $chainCodes  = array_keys($this->branchDefs());
        $chainIds    = [];
        $branchIds   = [];

        foreach ($this->branchDefs() as $code => $info) {
            DB::table('supermarket_chains')->updateOrInsert(
                ['code' => $code],
                [
                    'name'       => $info['chain_name'],
                    'status'     => 'active',
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                    'deleted_at' => null,
                ]
            );
            $chainId = (int) DB::table('supermarket_chains')->where('code', $code)->value('id');
            $chainIds[$code] = $chainId;

            $existingBranch = DB::table('supermarket_branches')
                ->where('supermarket_chain_id', $chainId)
                ->where('name', $info['branch'])
                ->first();

            $branchIds[$code] = $existingBranch
                ? (int) $existingBranch->id
                : (int) DB::table('supermarket_branches')->insertGetId([
                    'supermarket_chain_id' => $chainId,
                    'city_id'              => $cityId,
                    'name'                 => $info['branch'],
                    'address'              => 'Bariloche, Rio Negro',
                    'latitude'             => -41.1335,
                    'longitude'            => -71.3103,
                    'delivery_available'   => true,
                    'pickup_available'     => true,
                    'status'               => 'active',
                    'created_at'           => $this->now,
                    'updated_at'           => $this->now,
                    'deleted_at'           => null,
                ]);

            if (!$existingBranch) {
                continue;
            }
            DB::table('supermarket_branches')->where('id', $branchIds[$code])->update([
                'status' => 'active', 'deleted_at' => null, 'city_id' => $cityId, 'updated_at' => $this->now,
            ]);
        }

        // Promocion: 15% en lacteos en La Anonima (aplica a la leche).
        $promoChain = $chainIds['la_anonima'];
        $promoBranch = $branchIds['la_anonima'];
        $existingPromo = DB::table('promotions')
            ->where('supermarket_chain_id', $promoChain)
            ->where('name', 'La Anonima - 15% en lacteos')
            ->first();
        $promoId = $existingPromo
            ? (int) $existingPromo->id
            : (int) DB::table('promotions')->insertGetId([
                'supermarket_chain_id'    => $promoChain,
                'supermarket_branch_id'   => $promoBranch,
                'name'                    => 'La Anonima - 15% en lacteos',
                'description'             => 'Descuento del 15% en productos lacteos.',
                'discount_type'           => 'percent',
                'discount_value'          => 15,
                'valid_from'              => $this->now->copy()->subDay(),
                'valid_to'                => $this->now->copy()->addDays(30),
                'requires_payment_method' => false,
                'status'                  => 'active',
                'created_at'              => $this->now,
                'updated_at'              => $this->now,
                'deleted_at'              => null,
            ]);
        if ($existingPromo) {
            DB::table('promotions')->where('id', $promoId)->update([
                'valid_from' => $this->now->copy()->subDay(),
                'valid_to'   => $this->now->copy()->addDays(30),
                'status'     => 'active',
                'updated_at' => $this->now,
            ]);
        }

        // supermarket_products + precios por sucursal.
        $matrix = $this->priceMatrix();
        foreach ($matrix as $productKey => $prices) {
            $productId = $products[$productKey];
            foreach ($chainCodes as $i => $code) {
                $chainId  = $chainIds[$code];
                $branchId = $branchIds[$code];
                $price    = $prices[$i];

                $sp = DB::table('supermarket_products')
                    ->where('product_id', $productId)
                    ->where('supermarket_chain_id', $chainId)
                    ->where('supermarket_branch_id', $branchId)
                    ->first();

                $spId = $sp
                    ? (int) $sp->id
                    : (int) DB::table('supermarket_products')->insertGetId([
                        'product_id'            => $productId,
                        'supermarket_chain_id'  => $chainId,
                        'supermarket_branch_id' => $branchId,
                        'last_seen_at'          => $this->now,
                        'last_scraped_at'       => $this->now,
                        'scrape_status'         => 'ok',
                        'status'                => 'active',
                        'created_at'            => $this->now,
                        'updated_at'            => $this->now,
                    ]);
                if ($sp) {
                    DB::table('supermarket_products')->where('id', $spId)->update([
                        'status' => 'active', 'last_seen_at' => $this->now, 'last_scraped_at' => $this->now, 'updated_at' => $this->now,
                    ]);
                }

                $isDairy = $productKey === 'leche';
                $promoForRow = ($isDairy && $code === 'la_anonima') ? $promoId : null;

                DB::table('supermarket_product_prices')->where('supermarket_product_id', $spId)->delete();
                DB::table('supermarket_product_prices')->insert([
                    'supermarket_product_id' => $spId,
                    'price'                  => $price,
                    'unit_price'             => null,
                    'currency'               => 'ARS',
                    'price_type'             => 'regular',
                    'promotion_id'           => $promoForRow,
                    'scraped_at'             => $this->now,
                    'valid_from'             => $this->now->copy()->subDay(),
                    'valid_to'               => null,
                    'source'                 => 'global_catalog',
                    'status'                 => 'active',
                    'created_at'             => $this->now,
                ]);

                DB::table('branch_product_availability')->updateOrInsert(
                    ['supermarket_branch_id' => $branchId, 'supermarket_product_id' => $spId],
                    [
                        'is_available'    => true,
                        'last_checked_at' => $this->now,
                        'source'          => 'global_catalog',
                        'status'          => 'active',
                    ]
                );
            }
        }
    }

    // ─────────────────────────────────────────────────────────── helpers

    protected function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        return trim(preg_replace('/\s+/', ' ', $value));
    }
}
