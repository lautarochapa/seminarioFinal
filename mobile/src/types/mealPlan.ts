import type { RecipeSummary } from './recipe';

export type MealSlot = 'breakfast' | 'lunch' | 'snack' | 'dinner' | string;

export interface PlanningPeriod {
  start_date: string;
  end_date: string;
}

export interface MealPlanEntry {
  id: number;
  date: string;
  meal_type_id: number;
  meal_type?: { id: number; code: MealSlot; name: string } | null;
  recipe_id: number | null;
  recipe?: Pick<RecipeSummary, 'id' | 'name'> & { nombre?: string | null } | null;
  free_meal_description: string | null;
  is_eating_out: boolean;
  servings_total: number | null;
  notes: string | null;
  status: string;
  created_at?: string;
}

export interface MealPlan {
  id: number;
  family_group_id: number;
  created_by: number | null;
  period_type: string;
  start_date: string;
  end_date: string;
  mode: string;
  status: string;
  items?: MealPlanEntry[];
  created_at?: string;
  updated_at?: string;
}

export interface MealPlanFilters {
  page?: number;
  per_page?: number;
  status?: string;
  start_date?: string;
  end_date?: string;
}
