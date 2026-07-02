export type DataOrigin = 'manual' | 'demo' | 'scraping' | string;

export interface SupermarketChain {
  id: number;
  name: string;
  website_url: string | null;
  status: string;
  created_at?: string;
  updated_at?: string;
}

export interface City {
  id: number;
  name: string;
  province?: string | null;
}

export interface SupermarketBranch {
  id: number;
  name: string;
  address: string | null;
  latitude: number | string | null;
  longitude: number | string | null;
  opening_hours: string | null;
  delivery_available: boolean;
  pickup_available: boolean;
  status: string;
  distance_km?: number;
  chain?: Pick<SupermarketChain, 'id' | 'name'> | null;
  city?: City | null;
  created_at?: string;
  updated_at?: string;
}

export type BranchService = 'delivery' | 'pickup';

export interface BranchFilters {
  city_id?: number;
  chain_id?: number;
  service?: BranchService;
  radius?: number;
  search?: string;
}

export interface NearbyBranch extends SupermarketBranch {
  distance_km: number;
}

export interface SupermarketProduct {
  id: number;
  external_sku: string | null;
  source_url: string | null;
  source_name: string | null;
  last_scraped_at: string | null;
  status: string;
  product?: { id: number; name: string } | null;
  branch?: {
    id: number;
    name: string;
    address: string | null;
    chain?: Pick<SupermarketChain, 'id' | 'name'> | null;
  } | null;
  current_price: CurrentPrice | null;
  created_at?: string;
  updated_at?: string;
}

export interface CurrentPrice {
  id?: number;
  price: number | string;
  currency: string;
  captured_at?: string | null;
  scraped_at?: string | null;
  valid_from?: string | null;
  valid_to?: string | null;
  source?: DataOrigin;
  is_current?: boolean;
  status?: string;
}

export interface PriceComparison {
  product_id: number;
  prices: SupermarketProduct[];
  best: SupermarketProduct | null;
  partial_errors: PriceSourceError[];
}

export interface PriceSourceError {
  source: string;
  message: string;
}

export interface PriceHistoryEntry extends CurrentPrice {
  id: number;
  source: DataOrigin;
}

export interface Promotion {
  id: number;
  supermarket_chain_id: number | null;
  supermarket_branch_id: number | null;
  name: string;
  description: string | null;
  discount_type: string;
  discount_value: number | string | null;
  valid_from: string | null;
  valid_to: string | null;
  day_of_week: string | number | null;
  requires_payment_method: boolean;
  status: string;
  chain?: Pick<SupermarketChain, 'id' | 'name'> | null;
  branch?: Pick<SupermarketBranch, 'id' | 'name' | 'address'> | null;
  created_at?: string;
  updated_at?: string;
  deleted_at?: string | null;
}

export interface PaymentMethod {
  id: number;
  name: string;
  type: string;
  issuer: string | null;
  status: string;
  created_at?: string;
  updated_at?: string;
}

export interface Notification {
  id: number;
  type: string;
  title: string;
  message: string;
  channel: string;
  status: string;
  read_at: string | null;
  sent_at: string | null;
  created_at: string;
  family_group_id: number | null;
}

export interface NotificationPreference {
  notification_type: string;
  app_enabled: boolean;
  email_enabled: boolean;
  push_enabled: boolean;
  frequency: 'immediate' | 'daily' | 'weekly' | string;
}

export type NotificationPreferences = NotificationPreference[];

export type ReportPeriod = 'month' | 'quarter' | 'year';

export interface UserReport {
  key: string;
  title: string;
  value: string;
  subtitle?: string;
  progress?: number | null;
}
