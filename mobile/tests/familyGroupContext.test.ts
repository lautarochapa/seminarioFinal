import { secureStorage } from '../src/storage/secureStorage';

jest.mock('expo-secure-store', () => ({
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  getItemAsync: jest.fn().mockResolvedValue(null),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

const mockGet = require('expo-secure-store').getItemAsync as jest.Mock;
const mockSet = require('expo-secure-store').setItemAsync as jest.Mock;

beforeEach(() => {
  jest.clearAllMocks();
});

describe('secureStorage — selectedGroupId persistence', () => {
  it('persists group id as string', async () => {
    await secureStorage.setSelectedGroupId(7);
    expect(mockSet).toHaveBeenCalledWith('cc_selected_group_id', '7');
  });

  it('retrieves stored group id as number', async () => {
    mockGet.mockResolvedValueOnce('7');
    const id = await secureStorage.getSelectedGroupId();
    expect(id).toBe(7);
  });

  it('returns null when key is absent', async () => {
    mockGet.mockResolvedValueOnce(null);
    const id = await secureStorage.getSelectedGroupId();
    expect(id).toBeNull();
  });

  it('returns null when stored value is not numeric', async () => {
    mockGet.mockResolvedValueOnce('not-a-number');
    const id = await secureStorage.getSelectedGroupId();
    expect(id).toBeNull();
  });

  it('removes group id', async () => {
    const mockDel = require('expo-secure-store').deleteItemAsync as jest.Mock;
    await secureStorage.removeSelectedGroupId();
    expect(mockDel).toHaveBeenCalledWith('cc_selected_group_id');
  });
});
