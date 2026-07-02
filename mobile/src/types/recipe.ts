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
