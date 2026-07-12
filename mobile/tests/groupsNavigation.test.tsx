import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';
import { FamilyGroupsScreen } from '../src/screens/FamilyGroupsScreen';

jest.mock('@expo/vector-icons', () => ({
  MaterialCommunityIcons: 'MaterialCommunityIcons',
}));

jest.mock('expo-router', () => ({
  useRouter: () => ({
    navigate: jest.fn(),
    push: jest.fn(),
    back: jest.fn(),
    canGoBack: jest.fn(() => true),
    replace: jest.fn(),
  }),
  router: {
    canGoBack: jest.fn(() => true),
    back: jest.fn(),
    replace: jest.fn(),
  },
}));

jest.mock('react-native-safe-area-context', () => ({
  useSafeAreaInsets: () => ({ top: 24, bottom: 0, left: 0, right: 0 }),
}));

jest.mock('expo-secure-store', () => ({
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  getItemAsync: jest.fn().mockResolvedValue(null),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

const mockRestoreGroup = jest.fn().mockResolvedValue(undefined);
const mockSelectGroup = jest.fn();

jest.mock('../src/auth/FamilyGroupContext', () => ({
  useFamilyGroupContext: () => ({
    selectedGroup: null,
    selectGroup: mockSelectGroup,
    clearGroup: jest.fn(),
    restoreGroup: mockRestoreGroup,
  }),
}));

jest.mock('../src/hooks/useFamilyGroups', () => ({
  useFamilyGroups: (opts?: { onLoaded?: (g: unknown[]) => void }) => {
    const groups = [{ id: 1, name: 'Casa', status: 'active', default_address: null }];
    if (opts?.onLoaded) opts.onLoaded(groups);
    return { data: groups, loading: false, error: null, refresh: jest.fn() };
  },
}));

beforeEach(() => {
  jest.clearAllMocks();
});

describe('FamilyGroupsScreen — back navigation', () => {
  it('renders a visible back button', async () => {
    const { getByLabelText } = await render(<FamilyGroupsScreen />);
    expect(getByLabelText('Volver')).toBeTruthy();
  });

  it('back button calls router.back() when history exists', async () => {
    const expoRouter = require('expo-router');
    (expoRouter.router.canGoBack as jest.Mock).mockReturnValue(true);
    const { getByLabelText } = await render(<FamilyGroupsScreen />);
    fireEvent.press(getByLabelText('Volver'));
    expect(expoRouter.router.back).toHaveBeenCalledTimes(1);
    expect(expoRouter.router.replace).not.toHaveBeenCalled();
  });

  it('back button calls router.replace when no history', async () => {
    const expoRouter = require('expo-router');
    (expoRouter.router.canGoBack as jest.Mock).mockReturnValue(false);
    const { getByLabelText } = await render(<FamilyGroupsScreen />);
    fireEvent.press(getByLabelText('Volver'));
    expect(expoRouter.router.replace).toHaveBeenCalledWith('/(app)');
    expect(expoRouter.router.back).not.toHaveBeenCalled();
  });

  it('restoreGroup is called after groups load', async () => {
    await render(<FamilyGroupsScreen />);
    expect(mockRestoreGroup).toHaveBeenCalled();
  });
});
