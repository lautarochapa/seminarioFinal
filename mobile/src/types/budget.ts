export interface Budget {
  id: number;
  family_group_id: number;
  year: number;
  month: number;
  total_amount: number;
  currency: string;
  status: string;
  used_amount?: number;
  available_amount?: number;
  consumed_percent?: number;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
}

export interface BudgetSummary {
  budget_id: number;
  year: number;
  month: number;
  currency: string;
  total_amount: number;
  spent_amount: number;
  available_amount: number;
  consumed_percent: number;
  purchase_count: number;
}

export interface BudgetProjectionSource {
  shopping_list_id: number;
  source_type: string;
  meal_plan_id: number | null;
  estimated_total: number;
}

export interface BudgetProjection {
  budget_id: number;
  year: number;
  month: number;
  currency: string;
  total_amount: number;
  spent_amount: number;
  planned_amount: number | null;
  available_projected: number;
  percent_projected: number;
  planned_sources: BudgetProjectionSource[];
}

export interface BudgetCreateRequest {
  year: number;
  month: number;
  total_amount: number;
  currency?: 'ARS' | 'USD' | 'EUR';
}

export interface BudgetFilters {
  page?: number;
  per_page?: number;
  status?: string;
}
