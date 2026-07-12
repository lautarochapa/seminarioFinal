import { shoppingListsApi, shoppingListItemsApi } from '../src/api/endpoints';

jest.mock('../src/api/client', () => {
  const get = jest.fn();
  const post = jest.fn();
  const patch = jest.fn();
  const del = jest.fn();
  return {
    ApiError: class ApiError extends Error {
      normalized: unknown;
      constructor(n: unknown) { super('err'); this.normalized = n; this.name = 'ApiError'; }
    },
    apiClient: { get, post, patch, delete: del },
  };
});

jest.mock('expo-secure-store', () => ({
  setItemAsync: jest.fn(),
  getItemAsync: jest.fn().mockResolvedValue(null),
  deleteItemAsync: jest.fn(),
}));

const { apiClient } = require('../src/api/client');

const MOCK_LIST = {
  id: 1, family_group_id: 2, meal_plan_id: null,
  source_type: 'manual', status: 'active', estimated_total: null,
  optimization_mode: null, created_at: '2024-01-01T00:00:00Z', updated_at: '2024-01-01T00:00:00Z', deleted_at: null,
};

const MOCK_PAGINATED = {
  data: [MOCK_LIST],
  meta: { current_page: 1, per_page: 20, total: 1, last_page: 1 },
  links: { first: null, last: null, prev: null, next: null },
  trace_id: 'abc',
};

beforeEach(() => { jest.clearAllMocks(); });

describe('shoppingListsApi', () => {
  it('list: calls correct endpoint with groupId', async () => {
    apiClient.get.mockResolvedValueOnce(MOCK_PAGINATED);
    const res = await shoppingListsApi.list(5);
    expect(apiClient.get).toHaveBeenCalledWith(expect.stringContaining('/family-groups/5/shopping-lists'));
    expect(res.data).toHaveLength(1);
  });

  it('get: calls correct endpoint with listId', async () => {
    apiClient.get.mockResolvedValueOnce({ data: MOCK_LIST, trace_id: 'abc' });
    await shoppingListsApi.get(5, 1);
    expect(apiClient.get).toHaveBeenCalledWith('/api/v1/family-groups/5/shopping-lists/1');
  });

  it('create: posts to correct endpoint', async () => {
    apiClient.post.mockResolvedValueOnce({ data: MOCK_LIST, trace_id: 'abc' });
    await shoppingListsApi.create(5, { source_type: 'manual', status: 'active' });
    expect(apiClient.post).toHaveBeenCalledWith(
      '/api/v1/family-groups/5/shopping-lists',
      { source_type: 'manual', status: 'active' },
    );
  });

  it('delete: calls delete endpoint', async () => {
    apiClient.delete.mockResolvedValueOnce(undefined);
    await shoppingListsApi.delete(5, 1);
    expect(apiClient.delete).toHaveBeenCalledWith('/api/v1/family-groups/5/shopping-lists/1');
  });

  it('startSession: posts to start-session endpoint', async () => {
    apiClient.post.mockResolvedValueOnce({ data: { id: 10, shopping_list_id: 1, family_group_id: 5, user_id: 1, supermarket_branch_id: null, started_at: null, finished_at: null, status: 'active' }, trace_id: 'abc' });
    const res = await shoppingListsApi.startSession(5, 1);
    expect(apiClient.post).toHaveBeenCalledWith('/api/v1/family-groups/5/shopping-lists/1/start-session');
    expect(res.data.id).toBe(10);
  });
});

describe('shoppingListItemsApi', () => {
  it('list: fetches items for list', async () => {
    apiClient.get.mockResolvedValueOnce({ data: [], trace_id: 'abc' });
    await shoppingListItemsApi.list(5, 1);
    expect(apiClient.get).toHaveBeenCalledWith('/api/v1/family-groups/5/shopping-lists/1/items');
  });

  it('create: posts item with required fields', async () => {
    const item = { id: 1, product: { id: 3, name: 'Test' }, ingredient: null, quantity: 2, unit: null, estimated_price: null, actual_price: null, status: 'pending', notes: null, created_at: '', updated_at: '' };
    apiClient.post.mockResolvedValueOnce({ data: item, trace_id: 'abc' });
    await shoppingListItemsApi.create(5, 1, { product_id: 3, quantity: 2, unit_id: 7 });
    expect(apiClient.post).toHaveBeenCalledWith(
      '/api/v1/family-groups/5/shopping-lists/1/items',
      { product_id: 3, quantity: 2, unit_id: 7 },
    );
  });

  it('update: patches item status', async () => {
    apiClient.patch.mockResolvedValueOnce({ data: {}, trace_id: 'abc' });
    await shoppingListItemsApi.update(5, 1, 99, { status: 'purchased' });
    expect(apiClient.patch).toHaveBeenCalledWith(
      '/api/v1/family-groups/5/shopping-lists/1/items/99',
      { status: 'purchased' },
    );
  });

  it('delete: deletes item', async () => {
    apiClient.delete.mockResolvedValueOnce(undefined);
    await shoppingListItemsApi.delete(5, 1, 99);
    expect(apiClient.delete).toHaveBeenCalledWith('/api/v1/family-groups/5/shopping-lists/1/items/99');
  });
});
