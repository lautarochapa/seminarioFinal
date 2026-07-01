export interface ApiResponse<T> {
  data: T;
  trace_id: string;
  message?: string;
}

export interface PaginatedMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface PaginatedLinks {
  first: string | null;
  last: string | null;
  prev: string | null;
  next: string | null;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: PaginatedMeta;
  links: PaginatedLinks;
  trace_id: string;
}

export interface ApiErrorDetail {
  code: string;
  message: string;
  details: string[];
  field_errors: Record<string, string[]>;
}

export interface ApiErrorResponse {
  error: ApiErrorDetail;
  trace_id: string;
}

export interface NormalizedError {
  status: number;
  code: string;
  message: string;
  fieldErrors: Record<string, string[]>;
  traceId: string;
  isNetworkError: boolean;
  isTimeoutError: boolean;
  retryAfter?: number;
}
