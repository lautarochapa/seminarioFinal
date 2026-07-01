export interface StockLocation {
  id: number;
  family_group_id: number;
  name: string;
  type: string;
  status: string;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
}

export interface StockProduct {
  id: number;
  name: string;
}

export interface StockUnit {
  id: number;
  code: string;
  name: string;
  symbol: string;
}

export interface StockItemLocation {
  id: number;
  name: string;
  type: string;
}

export interface StockItem {
  id: number;
  family_group_id: number;
  product_id: number;
  stock_location_id: number | null;
  quantity: number;
  unit_id: number;
  expiration_date: string | null;
  purchase_price: number | null;
  status: string;
  product: StockProduct | null;
  location: StockItemLocation | null;
  unit: StockUnit | null;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
}

export interface StockCreateRequest {
  product_id: number;
  stock_location_id?: number | null;
  quantity: number;
  unit_id: number;
  expiration_date?: string | null;
  purchase_price?: number | null;
  status?: string;
}

export interface StockUpdateRequest {
  product_id?: number;
  stock_location_id?: number | null;
  quantity?: number;
  unit_id?: number;
  expiration_date?: string | null;
  purchase_price?: number | null;
  status?: string;
}

export interface StockFilters {
  page?: number;
  per_page?: number;
  search?: string;
  location_id?: number;
  status?: string;
}
