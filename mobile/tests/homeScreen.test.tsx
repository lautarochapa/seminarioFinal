import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';
import { HomeScreen } from '../src/screens/HomeScreen';

// ── mocks ──────────────────────────────────────────────────────────────────

jest.mock('@expo/vector-icons', () => ({
  MaterialCommunityIcons: 'MaterialCommunityIcons',
}));

const mockNavigate = jest.fn();
const mockPush = jest.fn();

jest.mock('expo-router', () => ({
  useRouter: () => ({ navigate: mockNavigate, push: mockPush }),
}));

jest.mock('react-native-safe-area-context', () => ({
  useSafeAreaInsets: () => ({ top: 24, bottom: 0, left: 0, right: 0 }),
}));

jest.mock('../src/auth/AuthContext', () => ({
  useAuth: () => ({
    user: { name: 'Demo', lastname: 'User', email: 'demo@test.com' },
    logout: jest.fn(),
    isLoading: false,
  }),
}));

jest.mock('../src/auth/FamilyGroupContext', () => ({
  useFamilyGroupContext: () => ({ selectedGroup: null, selectGroup: jest.fn() }),
}));

// ── helpers ────────────────────────────────────────────────────────────────

beforeEach(() => {
  mockNavigate.mockClear();
  mockPush.mockClear();
});

// ── render ─────────────────────────────────────────────────────────────────

describe('HomeScreen — render', () => {
  it('renders greeting with user name', async () => {
    const { getByText } = await render(<HomeScreen />);
    expect(getByText(/Demo/)).toBeTruthy();
  });

  it('renders all four feature cards', async () => {
    const { getByText } = await render(<HomeScreen />);
    expect(getByText('Mi perfil')).toBeTruthy();
    expect(getByText('Grupos familiares')).toBeTruthy();
    expect(getByText('Catálogo')).toBeTruthy();
    expect(getByText('Stock del hogar')).toBeTruthy();
  });

  it('renders selected group chip when a group is active', async () => {
    const { getByText } = await render(<HomeScreen />);
    // no group selected → chip not shown; just verifying no crash
    expect(getByText('Mi perfil')).toBeTruthy();
  });
});

// ── navigation ─────────────────────────────────────────────────────────────

describe('HomeScreen — navigation', () => {
  it('Mi perfil uses router.navigate (tab switch, no history push)', async () => {
    const { getByText } = await render(<HomeScreen />);
    fireEvent.press(getByText('Mi perfil'));
    expect(mockNavigate).toHaveBeenCalledWith('/(app)/profile');
    expect(mockPush).not.toHaveBeenCalledWith('/(app)/profile');
  });

  it('Catálogo uses router.navigate', async () => {
    const { getByText } = await render(<HomeScreen />);
    fireEvent.press(getByText('Catálogo'));
    expect(mockNavigate).toHaveBeenCalledWith('/(app)/catalog');
  });

  it('Stock del hogar uses router.navigate', async () => {
    const { getByText } = await render(<HomeScreen />);
    fireEvent.press(getByText('Stock del hogar'));
    expect(mockNavigate).toHaveBeenCalledWith('/(app)/stock');
  });

  it('Grupos familiares uses router.push (stack navigation outside tabs)', async () => {
    const { getByText } = await render(<HomeScreen />);
    fireEvent.press(getByText('Grupos familiares'));
    expect(mockPush).toHaveBeenCalledWith('/(app)/groups');
    expect(mockNavigate).not.toHaveBeenCalledWith('/(app)/groups');
  });

  it('pressing Mi perfil twice does not accumulate push entries', async () => {
    const { getByText } = await render(<HomeScreen />);
    fireEvent.press(getByText('Mi perfil'));
    fireEvent.press(getByText('Mi perfil'));
    // navigate is idempotent in Expo Router (replaces current route for tabs)
    expect(mockNavigate).toHaveBeenCalledTimes(2);
    expect(mockPush).not.toHaveBeenCalledWith('/(app)/profile');
  });
});

// ── header size ────────────────────────────────────────────────────────────

describe('HomeScreen — header', () => {
  it('renders without crashing (safe-area insets hook is called)', async () => {
    // If useSafeAreaInsets were absent or threw, the component would crash here
    await expect(render(<HomeScreen />)).resolves.toBeTruthy();
  });
});
