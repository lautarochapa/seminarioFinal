import React from 'react';
import { Keyboard, StyleSheet } from 'react-native';
import { act, fireEvent, render, waitFor } from '@testing-library/react-native';
import { FamilyGroupsScreen } from '../src/screens/FamilyGroupsScreen';

const mockCreate = jest.fn();
const mockRefresh = jest.fn();
const mockRestoreGroup = jest.fn();
let mockGroups: { id: number; name: string; status: string; default_address: null }[] = [];

jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));
jest.mock('expo-router', () => ({ useRouter: () => ({ push: jest.fn() }) }));
jest.mock('react-native-safe-area-context', () => ({
  useSafeAreaInsets: () => ({ top: 24, bottom: 24, left: 0, right: 0 }),
}));
jest.mock('../src/utils/navigation', () => ({ goBackOrHome: jest.fn() }));
jest.mock('../src/auth/FamilyGroupContext', () => ({
  useFamilyGroupContext: () => ({
    selectedGroup: null, selectGroup: jest.fn(), restoreGroup: mockRestoreGroup,
  }),
}));
jest.mock('../src/hooks/useFamilyGroups', () => ({
  useFamilyGroups: () => ({ data: mockGroups, loading: false, error: null, refresh: mockRefresh }),
}));
jest.mock('../src/api/endpoints', () => ({
  familyGroupsApi: { create: (...args: unknown[]) => mockCreate(...args) },
}));

describe('FamilyGroupsScreen creation form', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockGroups = [];
    mockCreate.mockResolvedValue({ data: { id: 1 } });
    jest.spyOn(Keyboard, 'dismiss').mockImplementation(() => undefined);
  });

  afterEach(() => { jest.restoreAllMocks(); });

  async function openForm() {
    const screen = await render(<FamilyGroupsScreen />);
    await fireEvent.press(screen.getByRole('button', { name: 'Crear grupo' }));
    return screen;
  }

  it('centers the empty-state action', async () => {
    const screen = await render(<FamilyGroupsScreen />);
    const button = screen.getByRole('button', { name: 'Crear grupo' });
    expect(StyleSheet.flatten(button.props.style).alignSelf).toBe('center');
  });

  it('replaces the full-height empty state with the focused form', async () => {
    const screen = await openForm();
    expect(screen.queryByText('Todavía no pertenecés a un grupo familiar.')).toBeNull();
    expect(screen.queryByRole('button', { name: 'Crear grupo' })).toBeNull();
    expect(screen.getByText('Nuevo grupo familiar')).toBeTruthy();
    expect(screen.getByLabelText('Nombre del grupo').props.autoFocus).toBe(true);
  });

  it('dismisses the keyboard and clears the form when cancelling', async () => {
    const screen = await openForm();
    await fireEvent.changeText(screen.getByLabelText('Nombre del grupo'), 'Borrador');
    await fireEvent.press(screen.getByRole('button', { name: 'Cancelar' }));
    expect(Keyboard.dismiss).toHaveBeenCalledTimes(1);
    expect(screen.queryByLabelText('Nombre del grupo')).toBeNull();
    await fireEvent.press(screen.getByRole('button', { name: 'Crear grupo' }));
    expect(screen.getByLabelText('Nombre del grupo').props.value).toBe('');
    expect(mockCreate).not.toHaveBeenCalled();
  });

  it('requires a nonblank name', async () => {
    const screen = await openForm();
    await fireEvent.changeText(screen.getByLabelText('Nombre del grupo'), '  ');
    await fireEvent.press(screen.getByRole('button', { name: 'Crear' }));
    expect(screen.getByText('El nombre del grupo es obligatorio.')).toBeTruthy();
    expect(mockCreate).not.toHaveBeenCalled();
  });

  it('creates the group, dismisses the keyboard and resets the loading state', async () => {
    const screen = await openForm();
    await fireEvent.changeText(screen.getByLabelText('Nombre del grupo'), '  Hogar de prueba  ');
    await fireEvent.press(screen.getByRole('button', { name: 'Crear' }));
    await waitFor(() => expect(mockRefresh).toHaveBeenCalledTimes(1));
    expect(mockCreate).toHaveBeenCalledWith({ name: 'Hogar de prueba' });
    expect(Keyboard.dismiss).toHaveBeenCalledTimes(1);
    expect(screen.queryByLabelText('Nombre del grupo')).toBeNull();
    await fireEvent.press(screen.getByRole('button', { name: 'Crear grupo' }));
    expect(screen.getByRole('button', { name: 'Crear' }).props.accessibilityState.busy).toBe(false);
    expect(screen.getByLabelText('Nombre del grupo').props.value).toBe('');
  });

  it('keeps the typed name on failure and allows retrying', async () => {
    mockCreate.mockRejectedValueOnce(new Error('Network failure'));
    const screen = await openForm();
    await fireEvent.changeText(screen.getByLabelText('Nombre del grupo'), 'Hogar de prueba');
    await fireEvent.press(screen.getByRole('button', { name: 'Crear' }));
    await waitFor(() => expect(screen.getByText('Error al crear el grupo.')).toBeTruthy());
    expect(screen.getByLabelText('Nombre del grupo').props.value).toBe('Hogar de prueba');
    expect(screen.getByRole('button', { name: 'Crear' }).props.accessibilityState.disabled).toBe(false);
    expect(Keyboard.dismiss).not.toHaveBeenCalled();
    await fireEvent.press(screen.getByRole('button', { name: 'Crear' }));
    await waitFor(() => expect(mockRefresh).toHaveBeenCalledTimes(1));
    expect(mockCreate).toHaveBeenCalledTimes(2);
  });

  it('does not send concurrent requests from the keyboard and button', async () => {
    let resolveCreate!: (value: unknown) => void;
    mockCreate.mockImplementationOnce(() => new Promise((resolve) => { resolveCreate = resolve; }));
    const screen = await openForm();
    await fireEvent.changeText(screen.getByLabelText('Nombre del grupo'), 'Hogar de prueba');
    const submit = screen.getByLabelText('Nombre del grupo').props.onSubmitEditing;
    await act(() => { void submit(); void submit(); });
    expect(mockCreate).toHaveBeenCalledTimes(1);
    expect(screen.getByRole('button', { name: 'Crear' }).props.accessibilityState.disabled).toBe(true);
    await act(() => { resolveCreate({ data: { id: 1 } }); });
    await waitFor(() => expect(mockRefresh).toHaveBeenCalledTimes(1));
  });

  it('replaces the existing list while editing and restores it on cancellation', async () => {
    mockGroups = [{ id: 1, name: 'Casa', status: 'active', default_address: null }];
    const screen = await render(<FamilyGroupsScreen />);
    await fireEvent.press(screen.getByRole('button', { name: 'Crear otro grupo' }));
    expect(screen.queryByText('Casa')).toBeNull();
    expect(screen.getByLabelText('Nombre del grupo')).toBeTruthy();
    await fireEvent.press(screen.getByRole('button', { name: 'Cancelar' }));
    expect(screen.getByText('Casa')).toBeTruthy();
  });
});
