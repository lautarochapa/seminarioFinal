import { apiClient } from './client';
import type { LoginRequest, LoginResponse, MeResponse } from '@/types/auth';
import type { ApiResponse, PaginatedResponse } from '@/types/api';
import type { Profile } from '@/types/profile';
import type {
  FamilyGroup,
  FamilyGroupMember,
  FamilyGroupsResponse,
  CreateFamilyGroupRequest,
  InviteMemberRequest,
} from '@/types/familyGroup';
import type { ProductSummary, ProductDetail, ProductFilters } from '@/types/product';
import type {
  StockItem,
  StockLocation,
  StockCreateRequest,
  StockUpdateRequest,
  StockFilters,
} from '@/types/stock';

export const authApi = {
  login(payload: LoginRequest): Promise<LoginResponse> {
    return apiClient.post<LoginResponse>('/api/v1/auth/login', payload, { skipAuth: true });
  },
  me(): Promise<MeResponse> {
    return apiClient.get<MeResponse>('/api/v1/auth/me');
  },
  logout(): Promise<void> {
    return apiClient.post<void>('/api/v1/auth/logout');
  },
};

export const profileApi = {
  get(): Promise<ApiResponse<Profile>> {
    return apiClient.get<ApiResponse<Profile>>('/api/v1/users/me/profile');
  },
};

export const familyGroupsApi = {
  list(): Promise<FamilyGroupsResponse> {
    return apiClient.get<FamilyGroupsResponse>('/api/v1/family-groups');
  },
  get(id: number): Promise<ApiResponse<FamilyGroup>> {
    return apiClient.get<ApiResponse<FamilyGroup>>(`/api/v1/family-groups/${id}`);
  },
  create(payload: CreateFamilyGroupRequest): Promise<ApiResponse<FamilyGroup>> {
    return apiClient.post<ApiResponse<FamilyGroup>>('/api/v1/family-groups', payload);
  },
  members(id: number): Promise<ApiResponse<FamilyGroupMember[]>> {
    return apiClient.get<ApiResponse<FamilyGroupMember[]>>(`/api/v1/family-groups/${id}/members`);
  },
  invite(id: number, payload: InviteMemberRequest): Promise<ApiResponse<unknown>> {
    return apiClient.post<ApiResponse<unknown>>(`/api/v1/family-groups/${id}/invitations`, payload);
  },
};

function toQueryString(params: Record<string, unknown>): string {
  const parts: string[] = [];
  Object.entries(params).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') {
      parts.push(`${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`);
    }
  });
  return parts.length ? `?${parts.join('&')}` : '';
}

export const productsApi = {
  list(filters?: ProductFilters): Promise<PaginatedResponse<ProductSummary>> {
    const qs = toQueryString({ per_page: 20, ...filters } as Record<string, unknown>);
    return apiClient.get<PaginatedResponse<ProductSummary>>(`/api/v1/products${qs}`);
  },
  get(id: number): Promise<ApiResponse<ProductDetail>> {
    return apiClient.get<ApiResponse<ProductDetail>>(`/api/v1/products/${id}`);
  },
  findByBarcode(barcode: string): Promise<ApiResponse<ProductDetail>> {
    return apiClient.get<ApiResponse<ProductDetail>>(`/api/v1/products/barcode/${encodeURIComponent(barcode)}`);
  },
};

export const stockApi = {
  list(groupId: number, filters?: StockFilters): Promise<PaginatedResponse<StockItem>> {
    const qs = toQueryString({ per_page: 20, ...filters } as Record<string, unknown>);
    return apiClient.get<PaginatedResponse<StockItem>>(`/api/v1/family-groups/${groupId}/stock${qs}`);
  },
  create(groupId: number, payload: StockCreateRequest): Promise<ApiResponse<StockItem>> {
    return apiClient.post<ApiResponse<StockItem>>(`/api/v1/family-groups/${groupId}/stock`, payload);
  },
  update(groupId: number, stockItemId: number, payload: StockUpdateRequest): Promise<ApiResponse<StockItem>> {
    return apiClient.patch<ApiResponse<StockItem>>(`/api/v1/family-groups/${groupId}/stock/${stockItemId}`, payload);
  },
  delete(groupId: number, stockItemId: number): Promise<void> {
    return apiClient.delete<void>(`/api/v1/family-groups/${groupId}/stock/${stockItemId}`);
  },
};

export const stockLocationsApi = {
  list(groupId: number): Promise<ApiResponse<StockLocation[]>> {
    return apiClient.get<ApiResponse<StockLocation[]>>(`/api/v1/family-groups/${groupId}/stock-locations`);
  },
  create(groupId: number, payload: { name: string; type?: string }): Promise<ApiResponse<StockLocation>> {
    return apiClient.post<ApiResponse<StockLocation>>(`/api/v1/family-groups/${groupId}/stock-locations`, payload);
  },
};
