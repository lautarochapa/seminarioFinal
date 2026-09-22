import React from 'react';
import { StyleSheet, Text } from 'react-native';
import { render } from '@testing-library/react-native';
import { StatusBar } from 'expo-status-bar';
import { ModalSurface } from '../src/components/ModalSurface';

jest.mock('expo-status-bar', () => ({ StatusBar: jest.fn(() => null) }));

it('keeps modal controls inside Android insets with dark status indicators', async () => {
  const screen = await render(<ModalSurface testID="surface" style={{ flex: 1 }}><Text>Contenido</Text></ModalSurface>);
  expect(StyleSheet.flatten(screen.getByTestId('surface').props.style)).toEqual({
    flex: 1, paddingTop: 24, paddingBottom: 24,
  });
  expect(jest.mocked(StatusBar).mock.calls[0][0].style).toBe('dark');
});
