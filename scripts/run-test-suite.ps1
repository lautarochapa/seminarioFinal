<#
.SYNOPSIS
    Runs the PHPUnit test suite partitioned by domain to avoid OOM failures.

.DESCRIPTION
    Executes Unit tests and each Feature/Api/V1 domain directory as a separate
    PHPUnit process at 512M memory limit. Stops on the first failing partition.
    This is the official strategy when the monolithic suite runs out of memory.

.PARAMETER MemoryLimit
    PHP memory_limit for each partition. Default: 512M.

.PARAMETER NoCoverage
    Skip coverage (recommended for speed). Default: true.

.PARAMETER Filter
    Optional regex filter passed to --filter for all partitions.

.EXAMPLE
    .\scripts\run-test-suite.ps1
    .\scripts\run-test-suite.ps1 -MemoryLimit 256M
    .\scripts\run-test-suite.ps1 -Filter RecipeSearch
#>
param(
    [string]$MemoryLimit = '512M',
    [switch]$NoCoverage  = $true,
    [string]$Filter      = ''
)

$php     = 'C:\xampp\php74\php.exe'
$phpunit = 'vendor\phpunit\phpunit\phpunit'
$root    = Split-Path $PSScriptRoot -Parent
$featureBase = Join-Path $root 'tests\Feature\Api\V1'

$partitionsFailed = [System.Collections.Generic.List[string]]::new()
$partitionsRun    = 0
$stopOnFail       = $true

function Invoke-Partition {
    param([string]$Label, [string]$Path)

    $phpArgs = @('-d', "memory_limit=$MemoryLimit", $phpunit)
    if ($NoCoverage)  { $phpArgs += '--no-coverage' }
    if ($Filter)      { $phpArgs += '--filter'; $phpArgs += $Filter }
    $phpArgs += $Path

    Write-Host ""
    Write-Host "━━━ $Label ━━━" -ForegroundColor Cyan

    # Pipe directly to host so stdout is NOT captured in the return value.
    & $php @phpArgs | Out-Host

    return $LASTEXITCODE -eq 0
}

$domains = [ordered]@{
    # Early / infra
    'Unit'                    = (Join-Path $root 'tests\Unit')
    'Feature/Auth'            = (Join-Path $featureBase 'Auth')
    'Feature/Admin'           = (Join-Path $featureBase 'Admin')
    'Feature/FeatureFlags'    = (Join-Path $featureBase 'FeatureFlags')
    'Feature/AiFoundation'    = (Join-Path $featureBase 'AiFoundation')
    'Feature/Consents'        = (Join-Path $featureBase 'Consents')

    # Catalog
    'Feature/Brands'               = (Join-Path $featureBase 'Brands')
    'Feature/Units'                = (Join-Path $featureBase 'Units')
    'Feature/Cities'               = (Join-Path $featureBase 'Cities')
    'Feature/FoodTags'             = (Join-Path $featureBase 'FoodTags')
    'Feature/ProductCategories'    = (Join-Path $featureBase 'ProductCategories')
    'Feature/Products'             = (Join-Path $featureBase 'Products')
    'Feature/Barcodes'             = (Join-Path $featureBase 'Barcodes')
    'Feature/ProductImages'        = (Join-Path $featureBase 'ProductImages')
    'Feature/ProductReports'       = (Join-Path $featureBase 'ProductReports')
    'Feature/PaymentMethods'       = (Join-Path $featureBase 'PaymentMethods')
    'Feature/Promotions'           = (Join-Path $featureBase 'Promotions')
    'Feature/Supermarkets'         = (Join-Path $featureBase 'Supermarkets')
    'Feature/SupermarketBranches'  = (Join-Path $featureBase 'SupermarketBranches')
    'Feature/SupermarketProducts'  = (Join-Path $featureBase 'SupermarketProducts')
    'Feature/SupermarketPrices'    = (Join-Path $featureBase 'SupermarketPrices')
    'Feature/SupermarketComparison'= (Join-Path $featureBase 'SupermarketComparison')

    # Ingredients & nutrition
    'Feature/Ingredients'           = (Join-Path $featureBase 'Ingredients')
    'Feature/IngredientCategories'  = (Join-Path $featureBase 'IngredientCategories')
    'Feature/IngredientEquivalences'= (Join-Path $featureBase 'IngredientEquivalences')
    'Feature/Nutrients'             = (Join-Path $featureBase 'Nutrients')

    # Recipes
    'Feature/RecipeCategories'       = (Join-Path $featureBase 'RecipeCategories')
    'Feature/RecipeTags'             = (Join-Path $featureBase 'RecipeTags')
    'Feature/MealTypes'              = (Join-Path $featureBase 'MealTypes')
    'Feature/Recipes'                = (Join-Path $featureBase 'Recipes')
    'Feature/AdminRecipes'           = (Join-Path $featureBase 'AdminRecipes')
    'Feature/RecipeIngredients'      = (Join-Path $featureBase 'RecipeIngredients')
    'Feature/RecipeSteps'            = (Join-Path $featureBase 'RecipeSteps')
    'Feature/RecipeCost'             = (Join-Path $featureBase 'RecipeCost')
    'Feature/RecipeNutrition'        = (Join-Path $featureBase 'RecipeNutrition')
    'Feature/RecipeAvailability'     = (Join-Path $featureBase 'RecipeAvailability')
    'Feature/RecipeSearch'           = (Join-Path $featureBase 'RecipeSearch')
    'Feature/RecipeSuggestions'      = (Join-Path $featureBase 'RecipeSuggestions')
    'Feature/RecipeSubstitutions'    = (Join-Path $featureBase 'RecipeSubstitutions')
    'Feature/RecipeSharingBranch'    = (Join-Path $featureBase 'RecipeSharingBranch')
    'Feature/RecipeFavoritesCooked'  = (Join-Path $featureBase 'RecipeFavoritesCooked')
    'Feature/RecipeImportCandidates' = (Join-Path $featureBase 'RecipeImportCandidates')
    'Feature/RecipeImportText'       = (Join-Path $featureBase 'RecipeImportText')
    'Feature/RecipeImportUrl'        = (Join-Path $featureBase 'RecipeImportUrl')
    'Feature/RecipeScraping'         = (Join-Path $featureBase 'RecipeScraping')

    # User & health
    'Feature/UserProfile'        = (Join-Path $featureBase 'UserProfile')
    'Feature/BodyMeasurement'    = (Join-Path $featureBase 'BodyMeasurement')
    'Feature/Objectives'         = (Join-Path $featureBase 'Objectives')
    'Feature/HealthPreferences'  = (Join-Path $featureBase 'HealthPreferences')
    'Feature/UserSupplements'    = (Join-Path $featureBase 'UserSupplements')
    'Feature/SupplementSchedules'= (Join-Path $featureBase 'SupplementSchedules')
    'Feature/PersonalReports'    = (Join-Path $featureBase 'PersonalReports')
    'Feature/Professional'       = (Join-Path $featureBase 'Professional')
    'Feature/Notifications'      = (Join-Path $featureBase 'Notifications')

    # Family & stock
    'Feature/FamilyGroup'     = (Join-Path $featureBase 'FamilyGroup')
    'Feature/HouseholdStock'  = (Join-Path $featureBase 'HouseholdStock')
    'Feature/StockLocations'  = (Join-Path $featureBase 'StockLocations')
    'Feature/StockMovements'  = (Join-Path $featureBase 'StockMovements')
    'Feature/StockAlerts'     = (Join-Path $featureBase 'StockAlerts')
    'Feature/StockScan'       = (Join-Path $featureBase 'StockScan')
    'Feature/StockExpiration' = (Join-Path $featureBase 'StockExpiration')
    'Feature/WasteReport'     = (Join-Path $featureBase 'WasteReport')

    # Meal plans
    'Feature/MealPlans'                  = (Join-Path $featureBase 'MealPlans')
    'Feature/MealPlanGeneration'         = (Join-Path $featureBase 'MealPlanGeneration')
    'Feature/MealPlanItems'              = (Join-Path $featureBase 'MealPlanItems')
    'Feature/MealPlanItemStatus'         = (Join-Path $featureBase 'MealPlanItemStatus')
    'Feature/MealPlanPortions'           = (Join-Path $featureBase 'MealPlanPortions')
    'Feature/MealPlanIncompatibilities'  = (Join-Path $featureBase 'MealPlanIncompatibilities')

    # Shopping & purchases
    'Feature/ShoppingLists'          = (Join-Path $featureBase 'ShoppingLists')
    'Feature/ShoppingListItems'      = (Join-Path $featureBase 'ShoppingListItems')
    'Feature/ShoppingListGeneration' = (Join-Path $featureBase 'ShoppingListGeneration')
    'Feature/ShoppingListPreview'    = (Join-Path $featureBase 'ShoppingListPreview')
    'Feature/ShoppingSessions'       = (Join-Path $featureBase 'ShoppingSessions')
    'Feature/ShoppingAlternatives'   = (Join-Path $featureBase 'ShoppingAlternatives')
    'Feature/Purchases'              = (Join-Path $featureBase 'Purchases')
    'Feature/PurchaseItems'          = (Join-Path $featureBase 'PurchaseItems')
    'Feature/PurchaseConfirmation'   = (Join-Path $featureBase 'PurchaseConfirmation')

    # Budgets
    'Feature/Budgets'          = (Join-Path $featureBase 'Budgets')
    'Feature/BudgetAlerts'     = (Join-Path $featureBase 'BudgetAlerts')
    'Feature/BudgetCategories' = (Join-Path $featureBase 'BudgetCategories')
    'Feature/BudgetMovements'  = (Join-Path $featureBase 'BudgetMovements')
    'Feature/BudgetSummary'    = (Join-Path $featureBase 'BudgetSummary')

    # Reports & documents
    'Feature/GroupReports'         = (Join-Path $featureBase 'GroupReports')
    'Feature/AdminReports'         = (Join-Path $featureBase 'AdminReports')
    'Feature/ReportExports'        = (Join-Path $featureBase 'ReportExports')
    'Feature/ThesisDocuments'      = (Join-Path $featureBase 'ThesisDocuments')
    'Feature/ThesisComments'       = (Join-Path $featureBase 'ThesisComments')
    'Feature/AdminThesisDocuments' = (Join-Path $featureBase 'AdminThesisDocuments')

    # Scraping & admin
    'Feature/Scraping'            = (Join-Path $featureBase 'Scraping')
    'Feature/ScrapingCandidates'  = (Join-Path $featureBase 'ScrapingCandidates')
    'Feature/ScrapingAlerts'      = (Join-Path $featureBase 'ScrapingAlerts')
    'Feature/PriceRefreshRequests'= (Join-Path $featureBase 'PriceRefreshRequests')
    'Feature/DemoScenarios'       = (Join-Path $featureBase 'DemoScenarios')
    'Feature/SystemSettings'      = (Join-Path $featureBase 'SystemSettings')

    # Misc
    'Feature/ExampleTest' = (Join-Path $root 'tests\Feature\ExampleTest.php')
}

foreach ($entry in $domains.GetEnumerator()) {
    $label = $entry.Key
    $path  = $entry.Value

    if (-not (Test-Path $path)) { continue }

    $ok = Invoke-Partition -Label $label -Path $path
    $partitionsRun++

    if ($ok) {
        Write-Host "OK: $label" -ForegroundColor Green
    } else {
        Write-Host "FAILED: $label" -ForegroundColor Red
        $partitionsFailed.Add($label)

        if ($stopOnFail) {
            Write-Host "`nStopped after first failure. Fix $label then re-run." -ForegroundColor Yellow
            exit 1
        }
    }
}

# ── Summary ─────────────────────────────────────────────────────────────────
Write-Host ""
Write-Host ('═' * 56) -ForegroundColor White
Write-Host "  Test suite complete: $partitionsRun partitions run" -ForegroundColor White

if ($partitionsFailed.Count -eq 0) {
    Write-Host "  All partitions passed." -ForegroundColor Green
    exit 0
} else {
    Write-Host "  Failed partitions ($($partitionsFailed.Count)):" -ForegroundColor Red
    foreach ($f in $partitionsFailed) {
        Write-Host "    - $f" -ForegroundColor Red
    }
    exit 1
}
