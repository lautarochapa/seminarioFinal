import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';
import { GroupDetailScreen } from '../src/screens/GroupDetailScreen';

const mockInvite = jest.fn();
const mockResend = jest.fn();
let mockUserId = 1;
jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));
jest.mock('react-native-safe-area-context', () => ({
  useSafeAreaInsets: () => ({ top: 24, bottom: 24, left: 0, right: 0 }),
}));
jest.mock('../src/utils/navigation', () => ({ goBackOrHome: jest.fn() }));
jest.mock('../src/auth/AuthContext', () => ({ useAuth: () => ({ user: { id: mockUserId } }) }));
jest.mock('../src/hooks/useFamilyGroupDetail', () => ({
  useFamilyGroupDetail: () => ({
    group: { id: 10, name: 'Hogar', owner_user_id: 1, status: 'active' },
    members: [{ id: 2, user_id: 2, role: 'admin', status: 'active', name: 'Admin', email: 'admin@example.com' }],
    loading: false, error: null, refresh: jest.fn(),
  }),
}));
jest.mock('../src/api/endpoints', () => ({ familyGroupsApi: {
  invite: (...args: unknown[]) => mockInvite(...args),
  resendInvitation: (...args: unknown[]) => mockResend(...args),
} }));

describe('Invitaciones y correo', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockUserId = 1;
    mockInvite.mockResolvedValue({ data: { id: 8, family_group_id: 10, email_delivery: { status: 'accepted' } } });
    mockResend.mockResolvedValue({ data: { id: 8, family_group_id: 10, email_delivery: { status: 'throttled' } } });
  });

  async function invite() {
    const screen = await render(<GroupDetailScreen groupId={10} />);
    await fireEvent.changeText(screen.getByLabelText('Email del invitado'), ' guest@example.com ');
    await fireEvent.press(screen.getByRole('button', { name: 'Enviar invitación' }));
    await waitFor(() => expect(mockInvite).toHaveBeenCalledTimes(1));
    return screen;
  }

  it('distingue aceptacion del proveedor de entrega y elimina la nota de desarrollo', async () => {
    const screen = await invite();
    expect(mockInvite).toHaveBeenCalledWith(10, { email: 'guest@example.com' });
    expect(screen.getByText(/El servicio de correo aceptó/)).toBeTruthy();
    expect(screen.queryByText(/En desarrollo/)).toBeNull();
    expect(screen.getByLabelText('Email del invitado').props.value).toBe('');
  });

  it.each([
    ['disabled', /todavía no está habilitado/],
    ['restricted', /modo de prueba/],
    ['failed', /No se pudo confirmar/],
  ])('muestra %s sin afirmar un envio exitoso', async (status, message) => {
    mockInvite.mockResolvedValue({ data: { id: 8, family_group_id: 10, email_delivery: { status } } });
    const screen = await invite();
    expect(screen.getByText(message)).toBeTruthy();
    expect(screen.queryByText(/El servicio de correo aceptó/)).toBeNull();
  });

  it('no inventa un estado si el backend anterior no lo informa', async () => {
    mockInvite.mockResolvedValue({ data: { id: 8, family_group_id: 10 } });
    const screen = await invite();
    expect(screen.getByText(/No se confirmó el envío/)).toBeTruthy();
  });

  it('reenvia la misma invitacion sin crear otra y muestra la espera', async () => {
    const screen = await invite();
    await fireEvent.press(screen.getByRole('button', { name: 'Reenviar correo' }));
    await waitFor(() => expect(mockResend).toHaveBeenCalledWith(10, 8));
    expect(mockInvite).toHaveBeenCalledTimes(1);
    expect(screen.getByText(/Esperá un minuto/)).toBeTruthy();
  });

  it('permite invitar al administrador del grupo', async () => {
    mockUserId = 2;
    const screen = await render(<GroupDetailScreen groupId={10} />);
    expect(screen.getByRole('button', { name: 'Enviar invitación' })).toBeTruthy();
  });

  it('no ofrece enviar a alguien que no administra el grupo', async () => {
    mockUserId = 3;
    const screen = await render(<GroupDetailScreen groupId={10} />);
    expect(screen.queryByRole('button', { name: 'Enviar invitación' })).toBeNull();
  });
});
