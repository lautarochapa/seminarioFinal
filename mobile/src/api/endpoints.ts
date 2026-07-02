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
import type {
  ShoppingList,
  ShoppingListItem,
  ShoppingListCreateRequest,
  ShoppingListItemCreateRequest,
  ShoppingListItemUpdateRequest,
  ShoppingSession,
  ShoppingSessionScan,
  ShoppingListFilters,
} from '@/types/shopping';
import type {
  Purchase,
  PurchaseCreateRequest,
  PurchaseUpdateRequest,
  PurchaseFilters,
} from '@/types/purchase';
import type {
  Budget,
  BudgetSummary,
  BudgetProjection,
  BudgetCreateRequest,
  BudgetFilters,
} from '@/types/budget';
import type { Unit } from '@/types/unit';

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

export const unitsApi = {
  list(): Promise<PaginatedResponse<Unit>> {
    return apiClient.get<PaginatedResponse<Unit>>('/api/v1/units?per_page=100');
  },
};

export const shoppingListsApi = {
  list(groupId: number, filters?: ShoppingListFilters): Promise<PaginatedResponse<ShoppingList>> {
    const qs = toQueryString({ per_page: 20, ...filters } as Record<string, unknown>);
    return apiClient.get<PaginatedResponse<ShoppingList>>(`/api/v1/family-groups/${groupId}/shopping-lists${qs}`);
  },
  get(groupId: number, listId: number): Promise<ApiResponse<ShoppingList>> {
    return apiClient.get<ApiResponse<ShoppingList>>(`/api/v1/family-groups/${groupId}/shopping-lists/${listId}`);
  },
  create(groupId: number, payload: ShoppingListCreateRequest): Promise<ApiResponse<ShoppingList>> {
    return apiClient.post<ApiResponse<ShoppingList>>(`/api/v1/family-groups/${groupId}/shopping-lists`, payload);
  },
  update(groupId: number, listId: number, payload: Partial<ShoppingListCreateRequest> & { status?: string }): Promise<ApiResponse<ShoppingList>> {
    return apiClient.patch<ApiResponse<ShoppingList>>(`/api/v1/family-groups/${groupId}/shopping-lists/${listId}`, payload);
  },
  delete(groupId: number, listId: number): Promise<void> {
    return apiClient.delete<void>(`/api/v1/family-groups/${groupId}/shopping-lists/${listId}`);
  },
  startSession(groupId: number, listId: number): Promise<ApiResponse<ShoppingSession>> {
    return apiClient.post<ApiResponse<ShoppingSession>>(`/api/v1/family-groups/${groupId}/shopping-lists/${listId}/start-session`);
  },
};

export const shoppingListItemsApi = {
  list(groupId: number, listId: number): Promise<ApiResponse<ShoppingListItem[]>> {
    return apiClient.get<ApiResponse<ShoppingListItem[]>>(`/api/v1/family-groups/${groupId}/shopping-lists/${listId}/items`);
  },
  create(groupId: number, listId: number, payload: ShoppingListItemCreateRequest): Promise<ApiResponse<ShoppingListItem>> {
    return apiClient.post<ApiResponse<ShoppingListItem>>(`/api/v1/family-groups/${groupId}/shopping-lists/${listId}/items`, payload);
  },
  update(groupId: number, listId: number, itemId: number, payload: ShoppingListItemUpdateRequest): Promise<ApiResponse<ShoppingListItem>> {
    return apiClient.patch<ApiResponse<ShoppingListItem>>(`/api/v1/family-groups/${groupId}/shopping-lists/${listId}/items/${itemId}`, payload);
  },
  delete(groupId: number, listId: number, itemId: number): Promise<void> {
    return apiClient.delete<void>(`/api/v1/family-groups/${groupId}/shopping-lists/${listId}/items/${itemId}`);
  },
};

export const shoppingSessionsApi = {
  update(groupId: number, sessionId: number, payload: { supermarket_branch_id?: number | null }): Promise<ApiResponse<ShoppingSession>> {
    return apiClient.patch<ApiResponse<ShoppingSession>>(`/api/v1/family-groups/${groupId}/shopping-sessions/${sessionId}`, payload);
  },
  finish(groupId: number, sessionId: number): Promise<ApiResponse<ShoppingSession>> {
    return apiClient.post<ApiResponse<ShoppingSession>>(`/api/v1/family-groups/${groupId}/shopping-sessions/${sessionId}/finish`);
  },
  scan(groupId: number, sessionId: number, payload: { barcode: string; quantity?: number; price?: number | null }): Promise<ApiResponse<ShoppingSessionScan>> {
    return apiClient.post<ApiResponse<ShoppingSessionScan>>(`/api/v1/family-groups/${groupId}/shopping-sessions/${sessionId}/scan`, payload);
  },
};

export const purchasesApi = {
  list(groupId: number, filters?: PurchaseFilters): Promise<PaginatedResponse<Purchase>> {
    const qs = toQueryString({ per_page: 20, ...filters } as Record<string, unknown>);
    return apiClient.get<PaginatedResponse<Purchase>>(`/api/v1/family-groups/${groupId}/purchases${qs}`);
  },
  get(groupId: number, purchaseId: number): Promise<ApiResponse<Purchase>> {
    return apiClient.get<ApiResponse<Purchase>>(`/api/v1/family-groups/${groupId}/purchases/${purchaseId}`);
  },
  create(groupId: number, payload: PurchaseCreateRequest): Promise<ApiResponse<Purchase>> {
    return apiClient.post<ApiResponse<Purchase>>(`/api/v1/family-groups/${groupId}/purchases`, payload);
  },
  update(groupId: number, purchaseId: number, payload: PurchaseUpdateRequest): Promise<ApiResponse<Purchase>> {
    return apiClient.patch<ApiResponse<Purchase>>(`/api/v1/family-groups/${groupId}/purchases/${purchaseId}`, payload);
  },
  delete(groupId: number, purchaseId: number): Promise<void> {
    return apiClient.delete<void>(`/api/v1/family-groups/${groupId}/purchases/${purchaseId}`);
  },
  confirm(groupId: number, purchaseId: number): Promise<ApiResponse<Purchase>> {
    return apiClient.post<ApiResponse<Purchase>>(`/api/v1/family-groups/${groupId}/purchases/${purchaseId}/confirm`);
  },
  addToStock(groupId: number, purchaseId: number): Promise<ApiResponse<unknown>> {
    return apiClient.post<ApiResponse<unknown>>(`/api/v1/family-groups/${groupId}/purchases/${purchaseId}/add-to-stock`);
  },
};

export const budgetsApi = {
  list(groupId: number, filters?: BudgetFilters): Promise<PaginatedResponse<Budget>> {
    const qs = toQueryString({ per_page: 20, ...filters } as Record<string, unknown>);
    return apiClient.get<PaginatedResponse<Budget>>(`/api/v1/family-groups/${groupId}/budgets${qs}`);
  },
  current(groupId: number): Promise<ApiResponse<Budget>> {
    return apiClient.get<ApiResponse<Budget>>(`/api/v1/family-groups/${groupId}/budgets/current`);
  },
  get(groupId: number, budgetId: number): Promise<ApiResponse<Budget>> {
    return apiClient.get<ApiResponse<Budget>>(`/api/v1/family-groups/${groupId}/budgets/${budgetId}`);
  },
  create(groupId: number, payload: BudgetCreateRequest): Promise<ApiResponse<Budget>> {
    return apiClient.post<ApiResponse<Budget>>(`/api/v1/family-groups/${groupId}/budgets`, payload);
  },
  update(groupId: number, budgetId: number, payload: Partial<BudgetCreateRequest>): Promise<ApiResponse<Budget>> {
    return apiClient.patch<ApiResponse<Budget>>(`/api/v1/family-groups/${groupId}/budgets/${budgetId}`, payload);
  },
  delete(groupId: number, budgetId: number): Promise<void> {
    return apiClient.delete<void>(`/api/v1/family-groups/${groupId}/budgets/${budgetId}`);
  },
  summary(groupId: number, budgetId: number): Promise<ApiResponse<BudgetSummary>> {
    return apiClient.get<ApiResponse<BudgetSummary>>(`/api/v1/family-groups/${groupId}/budgets/${budgetId}/summary`);
  },
  projection(groupId: number, budgetId: number): Promise<ApiResponse<BudgetProjection>> {
    return apiClient.get<ApiResponse<BudgetProjection>>(`/api/v1/family-groups/${groupId}/budgets/${budgetId}/projection`);
  },
};
