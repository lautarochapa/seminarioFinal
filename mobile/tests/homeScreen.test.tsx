import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';
import { HomeScreen } from '../src/screens/HomeScreen';
import { UserAccountMenu } from '../src/components/UserAccountMenu';

jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));
const mockPush = jest.fn();
jest.mock('expo-router', () => ({ useRouter: () => ({ push: mockPush }) }));
jest.mock('react-native-safe-area-context', () => ({ useSafeAreaInsets: () => ({ top: 24, bottom: 0, left: 0, right: 0 }) }));
jest.mock('../src/auth/AuthContext', () => ({ useAuth: () => ({ user: { id: 1, name: 'Demo', lastname: 'User', email: 'demo@test.com' }, logout: jest.fn() }) }));
jest.mock('../src/auth/FamilyGroupContext', () => ({ useFamilyGroupContext: () => ({ selectedGroup: { id: 2, name: 'Familia Demo', owner_user_id: 1 } }) }));
jest.mock('../src/api/endpoints', () => ({ homeApi: { summary: jest.fn().mockResolvedValue({ data: { stock: { products: 3, low_stock: 1, expiring: 2, expired: 0 }, recipes: { available: 4 }, shopping: { active_lists: 1, pending_items: 2 }, actions: [] } }) } }));

describe('HomeScreen account navigation', () => {
  beforeEach(() => jest.clearAllMocks());
  it('opens the account menu and shows user data and all personal routes', async () => {
    const screen = await render(<UserAccountMenu visible user={{ id: 1, name: 'Demo', lastname: 'User', email: 'demo@test.com' } as never} group={{ id: 2, name: 'Familia Demo', owner_user_id: 1 } as never} onClose={jest.fn()} onNavigate={mockPush} onLogout={jest.fn()} />);
    expect(screen.getByText('Demo User')).toBeTruthy();
    expect(screen.getByText(/Familia Demo/)).toBeTruthy();
    expect(screen.getByText('Mi perfil')).toBeTruthy();
    expect(screen.getByText('Mis objetivos')).toBeTruthy();
    expect(screen.getByText('Preferencias alimentarias')).toBeTruthy();
    expect(screen.getByText('Restricciones y alergias')).toBeTruthy();
  });
  it('navigates to goals instead of settings', async () => {
    const screen = await render(<UserAccountMenu visible user={{ id: 1, name: 'Demo', lastname: 'User', email: 'demo@test.com' } as never} group={{ id: 2, name: 'Familia Demo', owner_user_id: 1 } as never} onClose={jest.fn()} onNavigate={mockPush} onLogout={jest.fn()} />); fireEvent.press(screen.getByText('Mis objetivos'));
    expect(mockPush).toHaveBeenCalledWith('/(app)/goals');
    expect(mockPush).not.toHaveBeenCalledWith('/(app)/settings');
  });
  it('navigates summary cards with filters', async () => {
    const screen = await render(<HomeScreen />); await waitFor(() => expect(screen.getByLabelText('2 por vencer')).toBeTruthy()); fireEvent.press(screen.getByLabelText('2 por vencer'));
    expect(mockPush).toHaveBeenCalledWith({ pathname: '/(app)/stock', params: { filter: 'expiring' } });
  });
});
