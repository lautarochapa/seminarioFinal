import React from 'react';
import { useLocalSearchParams } from 'expo-router';
import { PurchaseDetailScreen } from '@/screens/PurchaseDetailScreen';

export default function PurchaseDetailRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <PurchaseDetailScreen purchaseId={parseInt(id, 10)} />;
}
