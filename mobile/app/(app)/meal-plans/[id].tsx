import { useLocalSearchParams } from 'expo-router';
import { MealPlanDetailScreen } from '@/screens/MealPlanDetailScreen';

export default function MealPlanDetailRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <MealPlanDetailScreen planId={parseInt(id, 10)} />;
}
