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

export interface ProductSummary {
  id: number;
  name: string;
  normalized_name: string | null;
  brand_id: number | null;
  category_id: number | null;
  ingredient_id: number | null;
  default_unit_id: number | null;
  net_quantity: number | null;
  barcode: string | null;
  description: string | null;
  status: string;
  brand: ProductBrand | null;
  category: ProductCategory | null;
  ingredient: ProductIngredient | null;
  unit: ProductUnit | null;
  images?: ProductImage[];
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
}

export type ProductDetail = ProductSummary;

export type BarcodeLookupResult = ProductDetail;

export interface ProductFilters {
  search?: string;
  page?: number;
  per_page?: number;
  sort?: string;
  order?: 'asc' | 'desc';
}
