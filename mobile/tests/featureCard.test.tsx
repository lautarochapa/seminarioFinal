import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';
import { FeatureCard } from '../src/components/FeatureCard';

jest.mock('@expo/vector-icons', () => ({
  MaterialCommunityIcons: 'MaterialCommunityIcons',
}));

describe('FeatureCard', () => {
  it('renders title', async () => {
    const { getByText } = await render(<FeatureCard icon="home-outline" title="Inicio" />);
    expect(getByText('Inicio')).toBeTruthy();
  });

  it('renders subtitle when provided', async () => {
    const { getByText } = await render(
      <FeatureCard icon="home-outline" title="Inicio" subtitle="Volver al inicio" />,
    );
    expect(getByText('Volver al inicio')).toBeTruthy();
  });

  it('does not render subtitle when not provided', async () => {
    const { queryByText } = await render(<FeatureCard icon="home-outline" title="Inicio" />);
    expect(queryByText('Volver al inicio')).toBeNull();
  });

  it('calls onPress when pressed', async () => {
    const onPress = jest.fn();
    const { getByRole } = await render(
      <FeatureCard icon="home-outline" title="Inicio" onPress={onPress} />,
    );
    fireEvent.press(getByRole('button'));
    expect(onPress).toHaveBeenCalledTimes(1);
  });

  it('does not call onPress when disabled', async () => {
    const onPress = jest.fn();
    const { getByRole } = await render(
      <FeatureCard icon="home-outline" title="Inicio" onPress={onPress} disabled />,
    );
    fireEvent.press(getByRole('button'));
    expect(onPress).not.toHaveBeenCalled();
  });

  it('renders numeric badge', async () => {
    const { getByText } = await render(
      <FeatureCard icon="home-outline" title="Notif" badge={5} />,
    );
    expect(getByText('5')).toBeTruthy();
  });

  it('caps badge display at 99+', async () => {
    const { getByText } = await render(
      <FeatureCard icon="home-outline" title="Notif" badge={150} />,
    );
    expect(getByText('99+')).toBeTruthy();
  });

  it('renders accessibilityLabel fallback from title', async () => {
    const { getByRole } = await render(
      <FeatureCard icon="home-outline" title="Mi Perfil" onPress={() => {}} />,
    );
    expect(getByRole('button', { name: 'Mi Perfil' })).toBeTruthy();
  });
});
