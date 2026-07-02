import React from 'react';
import { useLocalSearchParams } from 'expo-router';
import { ShoppingSessionScreen } from '@/screens/ShoppingSessionScreen';

export default function ShoppingSessionRoute() {
  const { listId, sessionId } = useLocalSearchParams<{ listId: string; sessionId: string }>();
  return <ShoppingSessionScreen listId={parseInt(listId, 10)} sessionId={parseInt(sessionId ?? '0', 10)} />;
}
