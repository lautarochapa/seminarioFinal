import React from 'react';
import { act, fireEvent, render, waitFor } from '@testing-library/react-native';
import { HomeScreen } from '../src/screens/HomeScreen';
import { UserAccountMenu } from '../src/components/UserAccountMenu';

jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));
const mockPush = jest.fn();
const mockSummary = jest.fn();
let mockSelectedGroup: { id: number; name: string; owner_user_id: number } | null;
let mockFocused = true;
jest.mock('expo-router', () => ({
  useRouter: () => ({ push: mockPush }),
  useFocusEffect: (cb: () => void | (() => void)) => {
    jest.requireActual<typeof import('react')>('react').useEffect(() => {
      if (mockFocused) return cb();
    }, [cb, mockFocused]);
  },
}));
jest.mock('react-native-safe-area-context', () => ({ useSafeAreaInsets: () => ({ top: 24, bottom: 0, left: 0, right: 0 }) }));
jest.mock('../src/auth/AuthContext', () => ({ useAuth: () => ({ user: { id: 1, name: 'Demo', lastname: 'User', email: 'demo@test.com' }, logout: jest.fn() }) }));
jest.mock('../src/auth/FamilyGroupContext', () => ({ useFamilyGroupContext: () => ({ selectedGroup: mockSelectedGroup }) }));
jest.mock('../src/api/endpoints', () => ({ homeApi: { summary: (...args: unknown[]) => mockSummary(...args) } }));

const SUMMARY = { data: { stock: { products: 3, low_stock: 1, expiring: 2, expired: 0 }, recipes: { available: 4 }, shopping: { active_lists: 1, pending_items: 2 }, actions: [] } };

describe('HomeScreen account navigation', () => {
  it('omits a missing lastname from the account menu', async () => {
    const screen = await render(<UserAccountMenu visible user={{ id: 1, name: 'Martina', lastname: null, email: 'qa@example.invalid' } as never} group={null} onClose={jest.fn()} onNavigate={mockPush} onLogout={jest.fn()} />);
    expect(screen.getByText('Martina')).toBeTruthy();
    expect(screen.queryByText(/null|undefined/)).toBeNull();
  });
  beforeEach(() => {
    jest.clearAllMocks();
    mockFocused = true;
    mockSelectedGroup = { id: 2, name: 'Familia Demo', owner_user_id: 1 };
    mockSummary.mockResolvedValue(SUMMARY);
  });
  it('opens the account menu and shows only MVP personal routes', async () => {
    const screen = await render(<UserAccountMenu visible user={{ id: 1, name: 'Demo', lastname: 'User', email: 'demo@test.com' } as never} group={{ id: 2, name: 'Familia Demo', owner_user_id: 1 } as never} onClose={jest.fn()} onNavigate={mockPush} onLogout={jest.fn()} />);
    expect(screen.getByText('Demo User')).toBeTruthy();
    expect(screen.getByText(/Familia Demo/)).toBeTruthy();
    expect(screen.getByText('Mi perfil')).toBeTruthy();
    expect(screen.getByText('Grupo familiar')).toBeTruthy();
    expect(screen.queryByText('Mis objetivos')).toBeNull();
    expect(screen.queryByText('Recetas cocinadas')).toBeNull();
    expect(screen.queryByText('Configuración')).toBeNull();
  });
  it('navigates to the family group from the account menu', async () => {
    const screen = await render(<UserAccountMenu visible user={{ id: 1, name: 'Demo', lastname: 'User', email: 'demo@test.com' } as never} group={{ id: 2, name: 'Familia Demo', owner_user_id: 1 } as never} onClose={jest.fn()} onNavigate={mockPush} onLogout={jest.fn()} />); fireEvent.press(screen.getByText('Grupo familiar'));
    expect(mockPush).toHaveBeenCalledWith('/(app)/groups');
  });
  it('navigates summary cards with filters', async () => {
    const screen = await render(<HomeScreen />); await waitFor(() => expect(screen.getByLabelText('2 por vencer')).toBeTruthy()); fireEvent.press(screen.getByLabelText('2 por vencer'));
    expect(mockPush).toHaveBeenCalledWith({ pathname: '/(app)/stock', params: { filter: 'expiring' } });
  });

  it('ignores an older home summary after the saved group is restored', async () => {
    let resolveOld!: (value: typeof SUMMARY) => void;
    mockSelectedGroup = null;
    mockSummary.mockReturnValueOnce(new Promise<typeof SUMMARY>((resolve) => { resolveOld = resolve; }));
    const screen = await render(<HomeScreen />);
    await waitFor(() => expect(mockSummary).toHaveBeenCalledWith(null));
    mockSelectedGroup = { id: 2, name: 'Familia Demo', owner_user_id: 1 };
    await screen.rerender(<HomeScreen />);
    await waitFor(() => expect(screen.getByLabelText('3 productos en tu cocina')).toBeTruthy());
    await act(async () => { resolveOld({ data: { ...SUMMARY.data, stock: { ...SUMMARY.data.stock, products: 99 } } }); });
    expect(screen.getByLabelText('3 productos en tu cocina')).toBeTruthy();
    expect(screen.queryByLabelText('99 productos en tu cocina')).toBeNull();
  });

  it('refreshes the home counters when returning after a stock change', async () => {
    const screen = await render(<HomeScreen />);
    await waitFor(() => expect(screen.getByLabelText('3 productos en tu cocina')).toBeTruthy());
    mockFocused = false;
    await screen.rerender(<HomeScreen />);
    mockSummary.mockResolvedValue({ data: { ...SUMMARY.data, stock: { ...SUMMARY.data.stock, products: 4 } } });
    mockFocused = true;
    await screen.rerender(<HomeScreen />);
    await waitFor(() => expect(screen.getByLabelText('4 productos en tu cocina')).toBeTruthy());
    expect(mockSummary).toHaveBeenCalledTimes(2);
  });
});
