import React from 'react';
import { useLocalSearchParams } from 'expo-router';
import { ShoppingListDetailScreen } from '@/screens/ShoppingListDetailScreen';

export default function ShoppingListDetailRoute() {
  const { id, returnTo, returnId, addRecipeId, addRecipeName } = useLocalSearchParams<{ id: string; returnTo?: string; returnId?: string; addRecipeId?: string; addRecipeName?: string }>();
  return <ShoppingListDetailScreen listId={parseInt(id, 10)} returnTo={returnTo} returnId={returnId} addRecipeId={addRecipeId} addRecipeName={addRecipeName} />;
}
