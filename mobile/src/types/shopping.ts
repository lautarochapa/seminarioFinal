export interface ShoppingList {
  id: number;
  family_group_id: number;
  meal_plan_id: number | null;
  source_type: 'manual' | 'meal_plan' | 'history' | 'recipe';
  status: 'draft' | 'active' | 'completed' | 'cancelled';
  estimated_total: number | null;
  optimization_mode: string | null;
  items?: ShoppingListItem[];
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
}

export interface ShoppingListItem {
  id: number;
  ingredient: { id: number; name: string } | null;
  product: { id: number; name: string } | null;
  quantity: number;
  unit: { id: number; code: string; symbol: string } | null;
  estimated_price: number | null;
  actual_price: number | null;
  status: 'pending' | 'purchased' | 'skipped' | 'cancelled';
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface ShoppingListCreateRequest {
  source_type?: 'manual' | 'meal_plan' | 'history';
  status?: 'draft' | 'active';
  optimization_mode?: string | null;
}

export interface ShoppingListItemCreateRequest {
  ingredient_id?: number | null;
  product_id?: number | null;
  quantity: number;
  unit_id: number;
  estimated_price?: number | null;
  status?: 'pending' | 'purchased' | 'skipped' | 'cancelled';
  notes?: string | null;
}

export interface ShoppingListItemUpdateRequest {
  quantity?: number;
  unit_id?: number;
  estimated_price?: number | null;
  actual_price?: number | null;
  status?: 'pending' | 'purchased' | 'skipped' | 'cancelled';
  notes?: string | null;
}

export interface ShoppingSession {
  id: number;
  shopping_list_id: number;
  family_group_id: number;
  user_id: number;
  supermarket_branch_id: number | null;
  purchase_id?: number | null;
  started_at: string | null;
  finished_at: string | null;
  status: string;
}

export interface ShoppingSessionScan {
  id: number;
  barcode: string;
  product_id: number | null;
  shopping_list_item_id: number | null;
  quantity: number | null;
  price: number | null;
  scan_result: string;
  created_at: string;
}

export interface StockUpdateWarning {
  shopping_list_item_id: number | null;
  reason: string;
}

export interface StockUpdateResult {
  purchase_id: number;
  stock_created_count: number;
  stock_updated_count: number;
  stock_skipped_count: number;
  stock_warnings: StockUpdateWarning[];
}

export interface ShoppingSessionFinishRequest {
  stock_location_id?: number | null;
}

export interface ShoppingSessionFinishResult {
  data: ShoppingSession;
  summary: StockUpdateResult;
  trace_id: string;
}

export interface ShoppingListFilters {
  page?: number;
  per_page?: number;
  status?: string;
}
