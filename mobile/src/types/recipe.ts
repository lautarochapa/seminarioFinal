import type { ShoppingList } from './shopping';

export interface RecipeCategory {
  id: number;
  name: string;
}

export interface RecipeTag {
  id: number;
  code?: string | null;
  name: string;
  type?: string | null;
}

export interface RecipeImage {
  image_url: string;
  is_primary: boolean;
}

export interface RecipeIngredient {
  id: number;
  ingredient_id: number;
  ingredient_name: string | null;
  quantity: number | null;
  unit_id: number | null;
  unit_name: string | null;
  is_optional: boolean;
  notes: string | null;
  sort_order: number | null;
}

export interface RecipeStep {
  step_number: number;
  description: string;
  estimated_minutes: number | null;
}

export interface RecipeSummary {
  id: number;
  name: string;
  description?: string | null;
  difficulty?: string | null;
  servings?: number | null;
  prep_time_minutes?: number | null;
  cook_time_minutes?: number | null;
  is_official?: boolean;
  is_verified?: boolean;
  status?: string;
  category_id?: number | null;
  category?: RecipeCategory | null;
  tags?: RecipeTag[];
  images?: RecipeImage[];
  tags_count?: number;
  ingredients_count?: number;
}

export interface RecipeDetail extends RecipeSummary {
  ingredients?: RecipeIngredient[];
  steps?: RecipeStep[];
}

export interface RecipeFilters {
  page?: number;
  per_page?: number;
  search?: string;
  category_id?: number;
  difficulty?: string;
  status?: string;
}

export interface RecipeFavorite {
  id: number;
  recipe_id: number;
  favorited_at: string;
  recipe?: RecipeSummary;
}

export interface RecipeSuggestion {
  recipe: RecipeSummary;
  reason?: string | null;
  score?: number | null;
  missing_ingredients_count?: number | null;
  available_ingredients_count?: number | null;
}

export interface GenerateShoppingListRequest {
  mode?: string;
}

export interface GenerateShoppingListResult {
  shopping_list_id?: number | null;
  created_items_count?: number | null;
  missing_ingredients?: unknown[];
}

export interface RecipeShoppingListRequest {
  servings?: number;
  shopping_list_id?: number;
  supermarket_branch_id?: number;
  supermarket_chain_id?: number;
}

export interface RecipeShoppingListUnmappedIngredient {
  ingredient_id: number | null;
  ingredient_name: string | null;
  reason: string;
}

export type PriceSource = 'branch' | 'chain' | 'group_history' | 'best_available' | null;

export interface GeneratedShoppingListItem {
  shopping_list_item_id: number;
  ingredient_id: number | null;
  product_id: number | null;
  requested_quantity: number;
  requested_unit_id: number;
  purchase_quantity: number;
  purchase_unit_id: number;
  estimated_unit_price: number | null;
  estimated_subtotal: number | null;
  price_source: PriceSource;
  price_updated_at: string | null;
  supermarket_branch_id: number | null;
  supermarket_chain_id: number | null;
}

export interface RecipeShoppingListResult {
  shopping_list: ShoppingList;
  items_added: number;
  items_skipped_duplicate: number;
  unmapped_ingredients: RecipeShoppingListUnmappedIngredient[];
  priced_items: GeneratedShoppingListItem[];
  estimated_total: number;
  items_without_price: number;
  warnings: string[];
}
