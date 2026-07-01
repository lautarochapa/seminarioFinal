<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            // Catalog foundations (units, nutrients, food tags, ingredient categories)
            IngredientCatalogSeeder::class,
            IngredientCategoryTaxonomySeeder::class,
            IngredientSupportingTaxonomiesSeeder::class,

            // Meal and supplement taxonomies
            MealTypeSeeder::class,
            SupplementTypeSeeder::class,

            // Supermarket catalog (cities, chains, payment methods)
            SupermarketCatalogSeeder::class,

            // System support (notification channels, feature flags)
            SupportingSystemSeeder::class,

            // Scraping sources
            ScrapingSourceSeeder::class,

            // User profile catalog (objectives, restrictions, health conditions)
            UserProfileCatalogSeeder::class,

            // Legacy data
            MarketSeeder::class,
            AddressSeeder::class,

            // Demo data (family groups, ingredients, products, recipes, stock, shopping)
            DemoDataSeeder::class,
        ]);
    }
}
