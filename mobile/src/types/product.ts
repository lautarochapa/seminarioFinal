export interface ProductBrand {
  id: number;
  name: string;
}

export interface ProductCategory {
  id: number;
  name: string;
}

export interface ProductIngredient {
  id: number;
  name: string;
}

export interface ProductUnit {
  id: number;
  code: string;
  name: string;
  symbol: string;
}

export interface ProductImage {
  id: number;
  url: string;
  is_primary: boolean;
}

export interface ProductStockItem {
  id: number;
  family_group_id: number;
  product_id: number;
  stock_location_id: number | null;
  quantity: number;
  unit_id: number;
  expiration_date: string | null;
  status: string;
  location: { id: number; name: string; type: string | null } | null;
  unit: ProductUnit | null;
}

export interface ProductSummary {
  id: number;
  name: string;
  normalized_name: string | null;
  brand_id: number | null;
  category_id: number | null;
  ingredient_id: number | null;
  default_unit_id: number | null;
  net_quantity: number | null;
  package_unit_id?: number | null;
  barcode: string | null;
  description: string | null;
  status: string;
  origin?: string | null;
  review_status?: string | null;
  family_group_id?: number | null;
  brand: ProductBrand | null;
  category: ProductCategory | null;
  ingredient: ProductIngredient | null;
  unit: ProductUnit | null;
  package_unit?: ProductUnit | null;
  images?: ProductImage[];
  stock_items?: ProductStockItem[];
  stock_summary?: {
    in_stock: boolean;
    items_count: number;
  };
  stock_entry_suggestion?: {
    quantity: number | null;
    unit_id: number | null;
    source: 'existing_stock' | 'package' | 'default_unit' | null;
    requires_unit_selection: boolean;
    existing_units: ProductUnit[];
  } | null;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
}

export type ProductDetail = ProductSummary;

export type BarcodeLookupResult = ProductDetail;

export interface ProductFilters {
  search?: string;
  family_group_id?: number;
  page?: number;
  per_page?: number;
  sort?: string;
  order?: 'asc' | 'desc';
}

export interface ProductRequestCreate {
  name: string;
  brand?: string;
  presentation?: string;
  barcode?: string;
  unit_id?: number;
  family_group_id?: number;
  comment?: string;
  source?: 'user_request' | 'barcode' | 'stock' | 'shopping_list';
}

export interface ManualProductStockCreate {
  product: {
    name: string;
    brand?: string;
    presentation?: string;
    barcode?: string;
    unit_id: number;
    net_quantity?: number;
    package_unit_id?: number;
    ingredient_id?: number;
    category_id?: number;
  };
  stock: {
    quantity: number;
    unit_id?: number;
    stock_location_id?: number | null;
    expiration_date?: string | null;
    purchase_price?: number | null;
  };
}
