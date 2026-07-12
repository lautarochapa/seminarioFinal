import React from 'react';
import { useLocalSearchParams } from 'expo-router';
import { ShoppingListDetailScreen } from '@/screens/ShoppingListDetailScreen';

export default function ShoppingListDetailRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <ShoppingListDetailScreen listId={parseInt(id, 10)} />;
}
