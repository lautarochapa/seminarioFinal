import { shoppingItemCanAutoAssociate } from '../src/screens/ShoppingListDetailScreen';
import type { ShoppingListItem } from '../src/types/shopping';

jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));
jest.mock('expo-router', () => ({ useRouter: () => ({}) }));

describe('shopping ingredient resolution', () => {
  it('enables recipe ingredients without a commercial product', () => {
    expect(shoppingItemCanAutoAssociate({ ingredient: { id: 4, name: 'Pollo' }, product: null } as ShoppingListItem)).toBe(true);
  });
  it('keeps free-text non-food items opt-in', () => {
    expect(shoppingItemCanAutoAssociate({ free_text_name: 'Detergente', ingredient: null, product: null } as ShoppingListItem)).toBe(false);
  });
});
