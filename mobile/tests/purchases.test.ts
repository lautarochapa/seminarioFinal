import { purchasesApi } from '../src/api/endpoints';

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

const MOCK_PURCHASE = {
  id: 1, family_group_id: 2, shopping_list_id: null, supermarket_branch_id: null,
  user_id: 1, payment_method_id: null, purchase_date: '2024-01-15',
  estimated_total: 1500, actual_total: null, status: 'pending',
  created_at: '2024-01-15T10:00:00Z', updated_at: '2024-01-15T10:00:00Z', deleted_at: null,
};

const MOCK_PAGINATED = {
  data: [MOCK_PURCHASE],
  meta: { current_page: 1, per_page: 20, total: 1, last_page: 1 },
  links: { first: null, last: null, prev: null, next: null },
  trace_id: 'abc',
};

beforeEach(() => { jest.clearAllMocks(); });

describe('purchasesApi', () => {
  it('list: calls correct endpoint', async () => {
    apiClient.get.mockResolvedValueOnce(MOCK_PAGINATED);
    const res = await purchasesApi.list(5);
    expect(apiClient.get).toHaveBeenCalledWith(expect.stringContaining('/family-groups/5/purchases'));
    expect(res.data).toHaveLength(1);
  });

  it('get: returns single purchase with items', async () => {
    apiClient.get.mockResolvedValueOnce({ data: { ...MOCK_PURCHASE, items: [] }, trace_id: 'abc' });
    const res = await purchasesApi.get(5, 1);
    expect(apiClient.get).toHaveBeenCalledWith('/api/v1/family-groups/5/purchases/1');
    expect(res.data.items).toEqual([]);
  });

  it('confirm: posts to confirm endpoint', async () => {
    apiClient.post.mockResolvedValueOnce({ data: { ...MOCK_PURCHASE, status: 'confirmed' }, trace_id: 'abc' });
    const res = await purchasesApi.confirm(5, 1);
    expect(apiClient.post).toHaveBeenCalledWith('/api/v1/family-groups/5/purchases/1/confirm');
    expect(res.data.status).toBe('confirmed');
  });

  it('delete: calls delete endpoint', async () => {
    apiClient.delete.mockResolvedValueOnce(undefined);
    await purchasesApi.delete(5, 1);
    expect(apiClient.delete).toHaveBeenCalledWith('/api/v1/family-groups/5/purchases/1');
  });
});
