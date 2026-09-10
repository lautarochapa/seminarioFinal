import { useLocalSearchParams } from 'expo-router';
import { MealPlanDetailScreen } from '@/screens/MealPlanDetailScreen';

export default function MealPlanDetailRoute() {
  const { id, addRecipeId, addRecipeName } = useLocalSearchParams<{ id: string; addRecipeId?: string; addRecipeName?: string }>();
  return (
    <MealPlanDetailScreen
      planId={parseInt(id, 10)}
      preselectRecipeId={addRecipeId ? parseInt(addRecipeId, 10) : null}
      preselectRecipeName={addRecipeName ?? null}
    />
  );
}
