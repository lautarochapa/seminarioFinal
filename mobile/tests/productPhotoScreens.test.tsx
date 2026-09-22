import React from 'react';
import { render } from '@testing-library/react-native';
import { CatalogScreen } from '../src/screens/CatalogScreen';
import { ProductDetailScreen } from '../src/screens/ProductDetailScreen';
import { StockScreen } from '../src/screens/StockScreen';
import { StockItemDetailScreen } from '../src/screens/StockItemDetailScreen';

const mockProduct = { id: 10, name: 'Arroz', status: 'active', images: [{ id: 2, image_url: 'https://cdn.example.com/rice.jpg', is_primary: true }] };
const mockStock = { id: 20, product_id: 10, product: mockProduct, quantity: 2, status: 'active' };
jest.mock('../src/hooks/useProducts', () => ({ useProducts: () => ({ data: [mockProduct], refresh: jest.fn(), loadMore: jest.fn() }) }));
jest.mock('../src/hooks/useProductDetail', () => ({ useProductDetail: () => ({ data: mockProduct, refresh: jest.fn() }) }));
jest.mock('../src/hooks/useStock', () => ({ useStock: () => ({ data: [mockStock], refresh: jest.fn(), loadMore: jest.fn() }) }));
jest.mock('../src/hooks/useStockItem', () => ({ useStockItem: () => ({ data: mockStock, refresh: jest.fn() }) }));
jest.mock('../src/auth/FamilyGroupContext', () => ({ useFamilyGroupContext: () => ({ selectedGroup: { id: 1 } }) }));
jest.mock('../src/components/FamilyGroupSelector', () => ({ FamilyGroupSelector: 'FamilyGroupSelector' }));
jest.mock('../src/components/StockAlertsBanner', () => ({ StockAlertsBanner: 'StockAlertsBanner' }));
jest.mock('../src/components/AppHeader', () => ({ AppHeader: 'AppHeader' }));
jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));
jest.mock('expo-router', () => ({ useRouter: () => ({ push: jest.fn() }), useFocusEffect: jest.fn() }));

it.each([
  ['catalog', <CatalogScreen key="catalog" />],
  ['product detail', <ProductDetailScreen key="product" productId={10} />],
  ['stock', <StockScreen key="stock" />],
  ['stock detail', <StockItemDetailScreen key="stock-detail" stockItemId={20} />],
])('renders the actual product photo in %s', async (_name, component) => {
  const screen = await render(component);
  expect(screen.getByLabelText('Foto de Arroz').props.source).toEqual({ uri: mockProduct.images[0].image_url });
});
