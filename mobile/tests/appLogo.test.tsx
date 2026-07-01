import React from 'react';
import { render } from '@testing-library/react-native';
import { AppLogo } from '../src/components/AppLogo';

jest.mock('@expo/vector-icons', () => ({
  MaterialCommunityIcons: 'MaterialCommunityIcons',
}));

describe('AppLogo', () => {
  it('renders accessibilityLabel', async () => {
    const { getByLabelText } = await render(<AppLogo />);
    expect(getByLabelText('CocinaComidaControl')).toBeTruthy();
  });

  it('renders brand name text for medium variant', async () => {
    const { getAllByText } = await render(<AppLogo variant="medium" />);
    expect(getAllByText('Cocina').length).toBeGreaterThan(0);
    expect(getAllByText('Control').length).toBeGreaterThan(0);
  });

  it('does not render text for iconOnly variant', async () => {
    const { queryByText } = await render(<AppLogo variant="iconOnly" />);
    expect(queryByText('Cocina')).toBeNull();
  });

  it('renders tagline for large variant', async () => {
    const { getByText } = await render(<AppLogo variant="large" />);
    expect(getByText('Tu cocina, tu presupuesto, tu hogar.')).toBeTruthy();
  });

  it('does not render tagline for small variant', async () => {
    const { queryByText } = await render(<AppLogo variant="small" />);
    expect(queryByText('Tu cocina, tu presupuesto, tu hogar.')).toBeNull();
  });

  it('renders inverted without throwing', async () => {
    await expect(render(<AppLogo variant="medium" inverted />)).resolves.toBeTruthy();
  });
});
