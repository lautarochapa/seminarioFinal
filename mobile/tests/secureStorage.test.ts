import * as SecureStore from 'expo-secure-store';
import { secureStorage } from '../src/storage/secureStorage';

const mockGet = SecureStore.getItemAsync as jest.Mock;
const mockSet = SecureStore.setItemAsync as jest.Mock;
const mockDel = SecureStore.deleteItemAsync as jest.Mock;

beforeEach(() => {
  jest.clearAllMocks();
});

describe('secureStorage.setToken / getToken', () => {
  it('stores and retrieves a token', async () => {
    mockGet.mockResolvedValueOnce('my-token');
    await secureStorage.setToken('my-token');
    const token = await secureStorage.getToken();
    expect(mockSet).toHaveBeenCalledWith('cc_access_token', 'my-token');
    expect(token).toBe('my-token');
  });

  it('returns null when no token is stored', async () => {
    mockGet.mockResolvedValueOnce(null);
    const token = await secureStorage.getToken();
    expect(token).toBeNull();
  });
});

describe('secureStorage.setUser / getUser', () => {
  it('stores and retrieves user as JSON', async () => {
    const user = { id: 1, name: 'Test', email: 'test@test.com' };
    mockGet.mockResolvedValueOnce(JSON.stringify(user));
    await secureStorage.setUser(user);
    const retrieved = await secureStorage.getUser<typeof user>();
    expect(mockSet).toHaveBeenCalledWith('cc_user', JSON.stringify(user));
    expect(retrieved).toEqual(user);
  });

  it('returns null when stored JSON is invalid', async () => {
    mockGet.mockResolvedValueOnce('not-valid-json{{{');
    const retrieved = await secureStorage.getUser();
    expect(retrieved).toBeNull();
  });
});

describe('secureStorage.clearAll', () => {
  it('removes token, user and selected group id', async () => {
    await secureStorage.clearAll();
    expect(mockDel).toHaveBeenCalledWith('cc_access_token');
    expect(mockDel).toHaveBeenCalledWith('cc_user');
    expect(mockDel).toHaveBeenCalledWith('cc_selected_group_id');
    expect(mockDel).toHaveBeenCalledTimes(3);
  });
});

describe('secureStorage.selectedGroupId', () => {
  it('stores and retrieves a numeric group id', async () => {
    mockGet.mockResolvedValueOnce('42');
    await secureStorage.setSelectedGroupId(42);
    const id = await secureStorage.getSelectedGroupId();
    expect(mockSet).toHaveBeenCalledWith('cc_selected_group_id', '42');
    expect(id).toBe(42);
  });

  it('returns null when no id is stored', async () => {
    mockGet.mockResolvedValueOnce(null);
    const id = await secureStorage.getSelectedGroupId();
    expect(id).toBeNull();
  });
});
