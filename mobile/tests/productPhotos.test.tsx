import React from 'react';
import { StyleSheet } from 'react-native';
import { fireEvent, render } from '@testing-library/react-native';
import { ProductPhoto } from '../src/components/ProductPhoto';
import { productImageUrl } from '../src/utils/productImages';
import type { ProductImage } from '../src/types/product';

jest.mock('../src/config/env', () => ({ ENV: { API_URL: 'https://api.example.com' } }));
jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));

const image = (url: string, primary = false, status = 'active'): ProductImage => ({
  id: 1, image_url: url, is_primary: primary, status,
});

it('uses the API image_url field and prefers the active primary photo without mutating data', () => {
  const images = [image('https://cdn.example.com/side.jpg'), image('https://cdn.example.com/front.jpg', true)];
  expect(productImageUrl(images)).toBe(images[1].image_url);
  expect(images[0].is_primary).toBe(false);
  expect(productImageUrl([image('https://cdn.example.com/old.jpg', true, 'inactive'), images[0]])).toBe(images[0].image_url);
});
it('resolves public storage paths against the API host, not the phone', () => {
  expect(productImageUrl([image('/storage/products/images/rice.jpg')])).toBe('https://api.example.com/storage/products/images/rice.jpg');
});
it.each(['', 'javascript:alert(1)', 'file:///secret', 'data:image/png;base64,test', 'https://user:password@cdn.example.com/a.jpg', 'https://'])('ignores unsafe or invalid photo URL %s', (url) => {
  expect(productImageUrl([image(url)])).toBeNull();
});
it('skips an invalid primary photo for another available photo', () => {
  expect(productImageUrl([image('file:///bad', true), image('https://cdn.example.com/good.jpg')])).toBe('https://cdn.example.com/good.jpg');
});
it('rejects cleartext photos in production', () => {
  const globals = globalThis as typeof globalThis & { __DEV__: boolean };
  const dev = globals.__DEV__;
  try {
    globals.__DEV__ = false;
    expect(productImageUrl([image('http://cdn.example.com/rice.jpg')])).toBeNull();
  } finally { globals.__DEV__ = dev; }
});
it('handles a product without images', async () => {
  expect(productImageUrl()).toBeNull();
  expect(productImageUrl([])).toBeNull();
  const screen = await render(<ProductPhoto name="Arroz" />);
  expect(screen.queryByLabelText('Foto de Arroz')).toBeNull();
});
it('keeps fixed dimensions and the full package visible', async () => {
  const screen = await render(<ProductPhoto name="Arroz" images={[image('https://cdn.example.com/rice.jpg')]} size={160} />);
  const photo = screen.getByLabelText('Foto de Arroz');
  expect(photo.props.source).toEqual({ uri: 'https://cdn.example.com/rice.jpg' });
  expect(photo.props.resizeMode).toBe('contain');
  expect(StyleSheet.flatten(photo.parent?.props.style)).toMatchObject({ width: 160, height: 160, flexShrink: 0 });
});
it('falls back after a failed download and loads a different product normally', async () => {
  const screen = await render(<ProductPhoto name="Arroz" images={[image('https://cdn.example.com/broken.jpg')]} />);
  await fireEvent(screen.getByLabelText('Foto de Arroz'), 'error', { nativeEvent: { error: '404' } });
  expect(screen.queryByLabelText('Foto de Arroz')).toBeNull();
  await screen.rerender(<ProductPhoto name="Leche" images={[image('https://cdn.example.com/milk.jpg')]} />);
  expect(screen.getByLabelText('Foto de Leche').props.source.uri).toBe('https://cdn.example.com/milk.jpg');
});
