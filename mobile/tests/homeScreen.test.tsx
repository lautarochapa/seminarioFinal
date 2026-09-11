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
});
