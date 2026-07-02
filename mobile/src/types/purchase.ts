export interface Purchase {
  id: number;
  family_group_id: number;
  shopping_list_id: number | null;
  supermarket_branch_id: number | null;
  user_id: number;
  payment_method_id: number | null;
  purchase_date: string | null;
  estimated_total: number | null;
  actual_total: number | null;
  status: string;
  items?: PurchaseItem[];
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
}

export interface PurchaseItem {
  id: number;
  product_id: number;
  product_name: string | null;
  quantity: number;
  unit_id: number;
  unit_name: string | null;
  unit_price: number | null;
  total_price: number | null;
  expiration_date: string | null;
}

export interface PurchaseCreateItem {
  product_id: number;
  quantity: number;
  unit_id: number;
  unit_price?: number | null;
  expiration_date?: string | null;
}

export interface PurchaseCreateRequest {
  purchase_date: string;
  supermarket_branch_id?: number | null;
  payment_method_id?: number | null;
  shopping_list_id?: number | null;
  estimated_total?: number | null;
  items: PurchaseCreateItem[];
}

export interface PurchaseUpdateRequest {
  purchase_date?: string;
  supermarket_branch_id?: number | null;
  payment_method_id?: number | null;
  estimated_total?: number | null;
  actual_total?: number | null;
  status?: string;
}

export interface PurchaseFilters {
  page?: number;
  per_page?: number;
  status?: string;
  from?: string;
  to?: string;
}
