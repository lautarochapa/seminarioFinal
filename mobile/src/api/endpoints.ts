import { apiClient } from './client';
import type {
  LoginRequest,
  LoginResponse,
  MeResponse,
  RegisterRequest,
  RegisterResponse,
  ForgotPasswordRequest,
  ForgotPasswordResponse,
  ResetPasswordRequest,
  ResetPasswordResponse,
} from '@/types/auth';
import type { ApiResponse, PaginatedResponse } from '@/types/api';
import type { Profile, ProfileUpdateRequest, HealthPreferenceCatalogItem, HealthPreferenceType, UserHealthPreference } from '@/types/profile';
import type {
  FamilyGroup,
  FamilyGroupMember,
  FamilyGroupsResponse,
  CreateFamilyGroupRequest,
  InviteMemberRequest,
} from '@/types/familyGroup';
import type { ProductSummary, ProductDetail, ProductFilters, ProductRequestCreate, ManualProductStockCreate } from '@/types/product';
import type {
  StockItem,
  StockAdjustRequest,
  StockLocation,
  StockCreateRequest,
  StockUpdateRequest,
  StockFilters,
  StockMovement,
  StockMovementRequest,
  StockScanRequest,
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
  ShoppingSessionFinishRequest,
  ShoppingSessionFinishResult,
  CompleteShoppingListRequest,
  CompleteShoppingListResult,
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
import type {
  RecipeCategory,
  RecipeCost,
  RecipeDetail,
  RecipeFavorite,
  RecipeFilters,
  CookRecipeRequest,
  CookRecipeResult,
  RecipeAvailability,
  RecipeNutrition,
  RecipeSummary,
  RecipeSuggestion,
  GenerateShoppingListResult,
  RecipeShoppingListRequest,
  RecipeShoppingListResult,
} from '@/types/recipe';
import type { MealPlan, MealPlanEntry, MealPlanFilters } from '@/types/mealPlan';
import { normalizeRecipeSuggestions } from '@/utils/recipeSuggestions';
import type {
  BranchFilters,
  City,
  CurrentPrice,
  Notification,
  NotificationPreferences,
  PaymentMethod,
  PriceComparison,
  PriceHistoryEntry,
  PriceHistoryFilters,
  Promotion,
  PromotionFilters,
  ReportPeriod,
  SupermarketBranch,
  SupermarketChain,
  SupermarketProduct,
} from '@/types/retail';

export const authApi = {
  login(payload: LoginRequest): Promise<LoginResponse> {
    return apiClient.post<LoginResponse>('/api/v1/auth/login', payload, { skipAuth: true });
  },
  register(payload: RegisterRequest): Promise<RegisterResponse> {
    return apiClient.post<RegisterResponse>('/api/v1/auth/register', payload, { skipAuth: true });
  },
  me(): Promise<MeResponse> {
    return apiClient.get<MeResponse>('/api/v1/auth/me');
  },
  logout(): Promise<void> {
    return apiClient.post<void>('/api/v1/auth/logout');
  },
  forgotPassword(payload: ForgotPasswordRequest): Promise<ForgotPasswordResponse> {
    return apiClient.post<ForgotPasswordResponse>('/api/v1/auth/forgot-password', payload, { skipAuth: true });
  },
  resetPassword(payload: ResetPasswordRequest): Promise<ResetPasswordResponse> {
    return apiClient.post<ResetPasswordResponse>('/api/v1/auth/reset-password', payload, { skipAuth: true });
  },
};

export const profileApi = {
  get(): Promise<ApiResponse<Profile>> {
    return apiClient.get<ApiResponse<Profile>>('/api/v1/users/me/profile');
  },
  update(payload: ProfileUpdateRequest): Promise<ApiResponse<Profile>> {
    return apiClient.patch<ApiResponse<Profile>>('/api/v1/users/me/profile', payload);
  },
};

export const healthPreferencesApi = {
  catalog(type: HealthPreferenceType): Promise<ApiResponse<HealthPreferenceCatalogItem[]>> {
    return apiClient.get<ApiResponse<HealthPreferenceCatalogItem[]>>(`/api/v1/catalog/${type}`);
  },
  list(type: HealthPreferenceType): Promise<ApiResponse<UserHealthPreference[]>> {
    return apiClient.get<ApiResponse<UserHealthPreference[]>>(`/api/v1/users/me/${type}`);
  },
  add(type: HealthPreferenceType, itemId: number, severity?: string, notes?: string): Promise<ApiResponse<UserHealthPreference>> {
    const key = type === 'allergies' ? 'allergy_id' : type === 'health-conditions' ? 'health_condition_id' : 'dietary_restriction_id';
    return apiClient.post<ApiResponse<UserHealthPreference>>(`/api/v1/users/me/${type}`, { [key]: itemId, severity, notes });
  },
  remove(type: HealthPreferenceType, relationId: number): Promise<void> {
    return apiClient.delete<void>(`/api/v1/users/me/${type}/${relationId}`);
  },
};

export const objectivesApi = {
  catalog(): Promise<ApiResponse<{ id: number; code: string; name: string; description?: string | null }[]>> {
    return apiClient.get<ApiResponse<{ id: number; code: string; name: string; description?: string | null }[]>>('/api/v1/catalog/objectives');
  },
};

export const homeApi = {
  summary(familyGroupId?: number | null): Promise<ApiResponse<{
    family_group_id: number | null;
    stock: { products: number; low_stock: number; expiring: number; expired: number };
    recipes: { available: number };
    shopping: { active_lists: number; pending_items: number };
    actions: { type: string; message: string }[];
  }>> {
    const qs = toQueryString({ family_group_id: familyGroupId || undefined });
    return apiClient.get<ApiResponse<{
      family_group_id: number | null;
      stock: { products: number; low_stock: number; expiring: number; expired: number };
      recipes: { available: number };
      shopping: { active_lists: number; pending_items: number };
      actions: { type: string; message: string }[];
    }>>(`/api/v1/users/me/home-summary${qs}`);
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

export function toQueryString(params: Record<string, unknown>): string {
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
  findByBarcode(barcode: string, familyGroupId?: number | null): Promise<ApiResponse<ProductDetail>> {
    const qs = toQueryString({ family_group_id: familyGroupId || undefined });
    return apiClient.get<ApiResponse<ProductDetail>>(`/api/v1/products/barcode/${encodeURIComponent(barcode)}${qs}`);
  },
};

export const productRequestsApi = {
  create(payload: ProductRequestCreate): Promise<ApiResponse<unknown>> {
    return apiClient.post<ApiResponse<unknown>>('/api/v1/product-requests', payload);
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
  createManualProduct(groupId: number, payload: ManualProductStockCreate): Promise<ApiResponse<unknown>> {
    return apiClient.post<ApiResponse<unknown>>(`/api/v1/family-groups/${groupId}/stock/manual-product`, payload);
  },
  update(groupId: number, stockItemId: number, payload: StockUpdateRequest): Promise<ApiResponse<StockItem>> {
    return apiClient.patch<ApiResponse<StockItem>>(`/api/v1/family-groups/${groupId}/stock/${stockItemId}`, payload);
  },
  delete(groupId: number, stockItemId: number): Promise<void> {
    return apiClient.delete<void>(`/api/v1/family-groups/${groupId}/stock/${stockItemId}`);
  },
  expiring(groupId: number): Promise<PaginatedResponse<StockItem>> {
    return apiClient.get<PaginatedResponse<StockItem>>(`/api/v1/family-groups/${groupId}/stock/expiring`);
  },
  lowStock(groupId: number): Promise<PaginatedResponse<StockItem>> {
    return apiClient.get<PaginatedResponse<StockItem>>(`/api/v1/family-groups/${groupId}/stock/low-stock`);
  },
  scan(groupId: number, payload: StockScanRequest): Promise<ApiResponse<unknown>> {
    return apiClient.post<ApiResponse<unknown>>(`/api/v1/family-groups/${groupId}/stock/scan`, payload);
  },
};

export const stockMovementsApi = {
  list(groupId: number, filters?: { page?: number; per_page?: number; product_id?: number; type?: string }): Promise<PaginatedResponse<StockMovement>> {
    const qs = toQueryString({ per_page: 20, ...filters } as Record<string, unknown>);
    return apiClient.get<PaginatedResponse<StockMovement>>(`/api/v1/family-groups/${groupId}/stock-movements${qs}`);
  },
  adjust(groupId: number, stockItemId: number, payload: StockAdjustRequest): Promise<ApiResponse<StockItem>> {
    return apiClient.post<ApiResponse<StockItem>>(`/api/v1/family-groups/${groupId}/stock/${stockItemId}/adjust`, payload);
  },
  consume(groupId: number, stockItemId: number, payload: StockMovementRequest): Promise<ApiResponse<StockItem>> {
    return apiClient.post<ApiResponse<StockItem>>(`/api/v1/family-groups/${groupId}/stock/${stockItemId}/consume`, payload);
  },
  discard(groupId: number, stockItemId: number, payload: StockMovementRequest): Promise<ApiResponse<StockItem>> {
    return apiClient.post<ApiResponse<StockItem>>(`/api/v1/family-groups/${groupId}/stock/${stockItemId}/discard`, payload);
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
  complete(groupId: number, listId: number, payload: CompleteShoppingListRequest): Promise<ApiResponse<CompleteShoppingListResult>> {
    return apiClient.post<ApiResponse<CompleteShoppingListResult>>(`/api/v1/family-groups/${groupId}/shopping-lists/${listId}/complete`, payload);
  },
  processPendingStock(groupId: number, listId: number, payload: CompleteShoppingListRequest): Promise<ApiResponse<CompleteShoppingListResult>> {
    return apiClient.post<ApiResponse<CompleteShoppingListResult>>(`/api/v1/family-groups/${groupId}/shopping-lists/${listId}/process-pending-stock`, payload);
  },
  start(groupId: number, listId: number): Promise<ApiResponse<ShoppingList>> {
    return apiClient.post<ApiResponse<ShoppingList>>(`/api/v1/family-groups/${groupId}/shopping-lists/${listId}/start`);
  },
  cancel(groupId: number, listId: number): Promise<ApiResponse<ShoppingList>> {
    return apiClient.post<ApiResponse<ShoppingList>>(`/api/v1/family-groups/${groupId}/shopping-lists/${listId}/cancel`);
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
  finish(groupId: number, sessionId: number, payload?: ShoppingSessionFinishRequest): Promise<ShoppingSessionFinishResult> {
    return apiClient.post<ShoppingSessionFinishResult>(`/api/v1/family-groups/${groupId}/shopping-sessions/${sessionId}/finish`, payload ?? {});
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

export const recipesApi = {
  list(filters?: RecipeFilters): Promise<PaginatedResponse<RecipeSummary>> {
    const qs = toQueryString({ per_page: 20, ...filters } as Record<string, unknown>);
    return apiClient.get<PaginatedResponse<RecipeSummary>>(`/api/v1/recipes${qs}`);
  },
  search(filters?: RecipeFilters): Promise<PaginatedResponse<RecipeSummary>> {
    const qs = toQueryString({ per_page: 20, ...filters } as Record<string, unknown>);
    return apiClient.get<PaginatedResponse<RecipeSummary>>(`/api/v1/recipes/search${qs}`);
  },
  get(id: number): Promise<ApiResponse<RecipeDetail>> {
    return apiClient.get<ApiResponse<RecipeDetail>>(`/api/v1/recipes/${id}`);
  },
  nutrition(id: number): Promise<ApiResponse<RecipeNutrition>> {
    return apiClient.get<ApiResponse<RecipeNutrition>>(`/api/v1/recipes/${id}/nutrition`);
  },
  cost(id: number, groupId?: number | null): Promise<ApiResponse<RecipeCost>> {
    const qs = toQueryString({ family_group_id: groupId || undefined });
    return apiClient.get<ApiResponse<RecipeCost>>(`/api/v1/recipes/${id}/cost${qs}`);
  },
  availability(id: number, groupId: number, servings: number): Promise<ApiResponse<RecipeAvailability>> {
    const qs = toQueryString({ family_group_id: groupId, servings });
    return apiClient.get<ApiResponse<RecipeAvailability>>(`/api/v1/recipes/${id}/availability${qs}`);
  },
  cook(id: number, payload: CookRecipeRequest): Promise<ApiResponse<CookRecipeResult>> {
    return apiClient.post<ApiResponse<CookRecipeResult>>(`/api/v1/recipes/${id}/cook`, payload);
  },
  categories(): Promise<PaginatedResponse<RecipeCategory>> {
    return apiClient.get<PaginatedResponse<RecipeCategory>>('/api/v1/recipe-categories?per_page=100');
  },
};

export const recipeFavoritesApi = {
  list(): Promise<PaginatedResponse<RecipeFavorite>> {
    return apiClient.get<PaginatedResponse<RecipeFavorite>>('/api/v1/users/me/favorite-recipes?per_page=100');
  },
  add(recipeId: number): Promise<ApiResponse<RecipeFavorite>> {
    return apiClient.post<ApiResponse<RecipeFavorite>>(`/api/v1/recipes/${recipeId}/favorite`);
  },
  remove(recipeId: number): Promise<void> {
    return apiClient.delete<void>(`/api/v1/recipes/${recipeId}/favorite`);
  },
};

export const recipeSuggestionsApi = {
  list(groupId?: number | null): Promise<PaginatedResponse<RecipeSuggestion>> {
    const qs = toQueryString({ family_group_id: groupId || undefined, per_page: 20 });
    return apiClient.get<unknown>(`/api/v1/recipes/suggestions${qs}`)
      .then((payload) => normalizeRecipeSuggestions(payload).response);
  },
  listWithDiagnostics(groupId?: number | null): Promise<{ response: PaginatedResponse<RecipeSuggestion>; invalidCount: number }> {
    const qs = toQueryString({ family_group_id: groupId || undefined, per_page: 20 });
    return apiClient.get<unknown>(`/api/v1/recipes/suggestions${qs}`)
      .then((payload) => normalizeRecipeSuggestions(payload));
  },
  available(groupId: number): Promise<PaginatedResponse<RecipeSuggestion>> {
    return apiClient.get<unknown>(`/api/v1/family-groups/${groupId}/recipes/available?per_page=20`)
      .then((payload) => normalizeRecipeSuggestions(payload).response);
  },
  almostAvailable(groupId: number): Promise<PaginatedResponse<RecipeSuggestion>> {
    return apiClient.get<unknown>(`/api/v1/family-groups/${groupId}/recipes/almost-available?per_page=20`)
      .then((payload) => normalizeRecipeSuggestions(payload).response);
  },
  byExpiringStock(groupId: number): Promise<PaginatedResponse<RecipeSuggestion>> {
    return apiClient.get<unknown>(`/api/v1/family-groups/${groupId}/recipes/by-expiring-stock?per_page=20`)
      .then((payload) => normalizeRecipeSuggestions(payload).response);
  },
};

export const mealPlansApi = {
  list(groupId: number, filters?: MealPlanFilters): Promise<PaginatedResponse<MealPlan>> {
    const qs = toQueryString({ per_page: 20, ...filters } as Record<string, unknown>);
    return apiClient.get<PaginatedResponse<MealPlan>>(`/api/v1/family-groups/${groupId}/meal-plans${qs}`);
  },
  get(groupId: number, planId: number): Promise<ApiResponse<MealPlan>> {
    return apiClient.get<ApiResponse<MealPlan>>(`/api/v1/family-groups/${groupId}/meal-plans/${planId}`);
  },
  entries(groupId: number, planId: number): Promise<ApiResponse<MealPlanEntry[]>> {
    return apiClient.get<ApiResponse<MealPlanEntry[]>>(`/api/v1/family-groups/${groupId}/meal-plans/${planId}/items`);
  },
  generateShoppingList(groupId: number, planId: number): Promise<ApiResponse<GenerateShoppingListResult>> {
    return apiClient.post<ApiResponse<GenerateShoppingListResult>>(`/api/v1/family-groups/${groupId}/meal-plans/${planId}/generate-shopping-list`);
  },
};

export const planningApi = mealPlansApi;

export const supermarketsApi = {
  list(): Promise<ApiResponse<SupermarketChain[]>> {
    return apiClient.get<ApiResponse<SupermarketChain[]>>('/api/v1/supermarkets');
  },
  get(id: number): Promise<ApiResponse<SupermarketChain>> {
    return apiClient.get<ApiResponse<SupermarketChain>>(`/api/v1/supermarkets/${id}`);
  },
};

export const citiesApi = {
  list(): Promise<PaginatedResponse<City>> {
    return apiClient.get<PaginatedResponse<City>>('/api/v1/cities?per_page=100');
  },
};

export const branchesApi = {
  list(filters?: BranchFilters): Promise<ApiResponse<SupermarketBranch[]>> {
    const qs = toQueryString((filters ?? {}) as unknown as Record<string, unknown>);
    return apiClient.get<ApiResponse<SupermarketBranch[]>>(`/api/v1/supermarket-branches${qs}`);
  },
  get(id: number): Promise<ApiResponse<SupermarketBranch>> {
    return apiClient.get<ApiResponse<SupermarketBranch>>(`/api/v1/supermarket-branches/${id}`);
  },
  nearby(payload: { lat: number; lng: number; radius: number }): Promise<ApiResponse<SupermarketBranch[]>> {
    const qs = toQueryString(payload as unknown as Record<string, unknown>);
    return apiClient.get<ApiResponse<SupermarketBranch[]>>(`/api/v1/supermarket-branches/nearby${qs}`);
  },
  products(id: number, filters?: { search?: string; page?: number; per_page?: number }): Promise<PaginatedResponse<SupermarketProduct>> {
    const qs = toQueryString({ per_page: 20, ...filters } as Record<string, unknown>);
    return apiClient.get<PaginatedResponse<SupermarketProduct>>(`/api/v1/supermarket-branches/${id}/products${qs}`);
  },
  promotions(id: number): Promise<ApiResponse<Promotion[]>> {
    return apiClient.get<ApiResponse<Promotion[]>>(`/api/v1/supermarket-branches/${id}/promotions`);
  },
};

export const pricesApi = {
  byProduct(productId: number): Promise<ApiResponse<SupermarketProduct[]>> {
    return apiClient.get<ApiResponse<SupermarketProduct[]>>(`/api/v1/products/${productId}/supermarket-prices`);
  },
  bestPrice(productId: number): Promise<ApiResponse<SupermarketProduct | null>> {
    return apiClient.get<ApiResponse<SupermarketProduct | null>>(`/api/v1/products/${productId}/best-price`);
  },
  compare(productId: number): Promise<PriceComparison> {
    return Promise.all([this.byProduct(productId), this.bestPrice(productId)])
      .then(([prices, best]) => ({
        product_id: productId,
        prices: prices.data,
        best: best.data,
        partial_errors: [],
      }));
  },
  history(productId: number, filters?: PriceHistoryFilters): Promise<PaginatedResponse<PriceHistoryEntry>> {
    const qs = toQueryString({ per_page: 20, ...filters } as Record<string, unknown>);
    return apiClient.get<PaginatedResponse<PriceHistoryEntry>>(`/api/v1/products/${productId}/price-history${qs}`);
  },
};

export const promotionsApi = {
  byBranch(branchId: number): Promise<ApiResponse<Promotion[]>> {
    return branchesApi.promotions(branchId);
  },
  global(filters?: PromotionFilters): Promise<PaginatedResponse<Promotion>> {
    const qs = toQueryString({ per_page: 20, ...filters } as Record<string, unknown>);
    return apiClient.get<PaginatedResponse<Promotion>>(`/api/v1/promotions${qs}`);
  },
};

export const recipeShoppingListApi = {
  generate(groupId: number, recipeId: number, payload?: RecipeShoppingListRequest): Promise<ApiResponse<RecipeShoppingListResult>> {
    return apiClient.post<ApiResponse<RecipeShoppingListResult>>(`/api/v1/family-groups/${groupId}/recipes/${recipeId}/shopping-list`, payload ?? {});
  },
};

export const paymentMethodsApi = {
  list(): Promise<ApiResponse<PaymentMethod[]>> {
    return apiClient.get<ApiResponse<PaymentMethod[]>>('/api/v1/payment-methods');
  },
  userMethods(): Promise<ApiResponse<PaymentMethod[]>> {
    return apiClient.get<ApiResponse<PaymentMethod[]>>('/api/v1/users/me/payment-methods');
  },
};

export const notificationsApi = {
  list(filters?: { type?: string; status?: string; channel?: string; page?: number; per_page?: number }): Promise<PaginatedResponse<Notification>> {
    const qs = toQueryString({ per_page: 20, ...filters } as Record<string, unknown>);
    return apiClient.get<PaginatedResponse<Notification>>(`/api/v1/notifications${qs}`);
  },
  unreadCount(): Promise<ApiResponse<{ unread_count: number }>> {
    return apiClient.get<ApiResponse<{ unread_count: number }>>('/api/v1/notifications/unread-count');
  },
  markAsRead(id: number): Promise<ApiResponse<Notification>> {
    return apiClient.patch<ApiResponse<Notification>>(`/api/v1/notifications/${id}/read`);
  },
  markAllAsRead(): Promise<ApiResponse<{ marked_count: number }>> {
    return apiClient.patch<ApiResponse<{ marked_count: number }>>('/api/v1/notifications/read-all');
  },
  preferences(): Promise<ApiResponse<NotificationPreferences>> {
    return apiClient.get<ApiResponse<NotificationPreferences>>('/api/v1/users/me/notification-preferences');
  },
  updatePreferences(preferences: NotificationPreferences): Promise<ApiResponse<NotificationPreferences>> {
    return apiClient.patch<ApiResponse<NotificationPreferences>>('/api/v1/users/me/notification-preferences', { preferences });
  },
};

export const reportsApi = {
  stock(groupId: number): Promise<ApiResponse<Record<string, unknown>>> {
    return apiClient.get<ApiResponse<Record<string, unknown>>>(`/api/v1/family-groups/${groupId}/reports/stock`);
  },
  stockValue(groupId: number): Promise<ApiResponse<Record<string, unknown>>> {
    return apiClient.get<ApiResponse<Record<string, unknown>>>(`/api/v1/family-groups/${groupId}/reports/stock-value`);
  },
  waste(groupId: number, period?: ReportPeriod): Promise<ApiResponse<Record<string, unknown>>> {
    const qs = toQueryString(periodToDates(period));
    return apiClient.get<ApiResponse<Record<string, unknown>>>(`/api/v1/family-groups/${groupId}/reports/waste${qs}`);
  },
  purchases(groupId: number, period?: ReportPeriod): Promise<ApiResponse<Record<string, unknown>>> {
    const qs = toQueryString(periodToDates(period));
    return apiClient.get<ApiResponse<Record<string, unknown>>>(`/api/v1/family-groups/${groupId}/reports/purchases${qs}`);
  },
  budget(groupId: number): Promise<ApiResponse<Record<string, unknown>>> {
    return apiClient.get<ApiResponse<Record<string, unknown>>>(`/api/v1/family-groups/${groupId}/reports/budget`);
  },
  budgetVsActual(groupId: number): Promise<ApiResponse<Record<string, unknown>>> {
    return apiClient.get<ApiResponse<Record<string, unknown>>>(`/api/v1/family-groups/${groupId}/reports/budget-vs-actual`);
  },
  expiringProducts(groupId: number): Promise<ApiResponse<Record<string, unknown>>> {
    return apiClient.get<ApiResponse<Record<string, unknown>>>(`/api/v1/family-groups/${groupId}/reports/expiring-products`);
  },
  recipesCooked(groupId: number, period?: ReportPeriod): Promise<ApiResponse<Record<string, unknown>>> {
    const qs = toQueryString(periodToDates(period));
    return apiClient.get<ApiResponse<Record<string, unknown>>>(`/api/v1/family-groups/${groupId}/reports/recipes-cooked${qs}`);
  },
  nutritionEstimate(groupId: number, period?: ReportPeriod): Promise<ApiResponse<Record<string, unknown>>> {
    const qs = toQueryString(periodToDates(period));
    return apiClient.get<ApiResponse<Record<string, unknown>>>(`/api/v1/family-groups/${groupId}/reports/nutrition-estimate${qs}`);
  },
};

function periodToDates(period?: ReportPeriod): Record<string, string> {
  if (!period) return {};
  const now = new Date();
  const from = new Date(now);
  if (period === 'year') from.setMonth(now.getMonth() - 12);
  else if (period === 'quarter') from.setMonth(now.getMonth() - 3);
  else from.setMonth(now.getMonth() - 1);
  return {
    date_from: from.toISOString().slice(0, 10),
    date_to: now.toISOString().slice(0, 10),
  };
}

export type { CurrentPrice };
