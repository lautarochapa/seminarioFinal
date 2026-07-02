import React from 'react';
import { useLocalSearchParams } from 'expo-router';
import { ShoppingListEditScreen } from '@/screens/ShoppingListEditScreen';

export default function ShoppingListEditRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <ShoppingListEditScreen listId={parseInt(id, 10)} />;
}
