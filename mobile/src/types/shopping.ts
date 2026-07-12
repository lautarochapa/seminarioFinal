export type ShoppingListStatus = 'draft' | 'active' | 'in_progress' | 'completed' | 'cancelled';

export const SHOPPING_LIST_STATUS_LABELS: Record<ShoppingListStatus, string> = {
  draft: 'Borrador',
  active: 'Lista para comprar',
  in_progress: 'En compra',
  completed: 'Completada',
  cancelled: 'Cancelada',
};

export interface ShoppingList {
  id: number;
  family_group_id: number;
  meal_plan_id: number | null;
  source_type: 'manual' | 'meal_plan' | 'history' | 'recipe';
  status: ShoppingListStatus;
  status_label?: string;
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
  free_text_name?: string | null;
  display_name?: string | null;
  quantity: number | null;
  unit: { id: number; code: string; symbol: string } | null;
  estimated_price: number | null;
  estimated_subtotal: number | null;
  actual_price: number | null;
  status: 'pending' | 'purchased' | 'skipped' | 'cancelled';
  notes: string | null;
  price_source: 'branch' | 'chain' | 'group_history' | 'best_available' | 'manual' | null;
  price_updated_at: string | null;
  supermarket_chain_id: number | null;
  supermarket_branch_id: number | null;
  source_type: string | null;
  source_id: number | null;
  stock_processed_at?: string | null;
  purchase_item_id?: number | null;
  stock_processing_state?: 'pending' | 'processed' | 'omitted';
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
  free_text_name?: string | null;
  quantity?: number | null;
  unit_id?: number | null;
  estimated_price?: number | null;
  status?: 'pending' | 'purchased' | 'skipped' | 'cancelled';
  notes?: string | null;
}

export interface ShoppingListItemUpdateRequest {
  quantity?: number;
  unit_id?: number;
  free_text_name?: string | null;
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

export interface CompleteShoppingListItemRequest {
  shopping_list_item_id: number;
  add_to_stock: boolean;
  product_id?: number | null;
  create_pending_product?: boolean;
  name?: string;
  brand?: string;
  presentation?: string;
  quantity?: number;
  unit_id?: number;
  stock_location_id?: number | null;
  expiration_date?: string | null;
  actual_price?: number | null;
}

export interface CompleteShoppingListRequest {
  stock_location_id?: number | null;
  items: CompleteShoppingListItemRequest[];
}

export interface CompleteShoppingListWarning {
  shopping_list_item_id: number;
  reason: string;
}

export interface CompleteShoppingListResult {
  purchase: { id: number; estimated_total: number | null; actual_total: number | null; status: string };
  shopping_list: ShoppingList;
  items_purchased_count: number;
  items_added_to_stock_count: number;
  items_omitted_count: number;
  stock_items_created: number;
  stock_items_updated: number;
  stock_movements_created: number;
  warnings: CompleteShoppingListWarning[];
}
