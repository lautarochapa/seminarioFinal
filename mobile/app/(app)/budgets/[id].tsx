import React from 'react';
import { useLocalSearchParams } from 'expo-router';
import { BudgetDetailScreen } from '@/screens/BudgetDetailScreen';

export default function BudgetDetailRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <BudgetDetailScreen budgetId={parseInt(id, 10)} />;
}
