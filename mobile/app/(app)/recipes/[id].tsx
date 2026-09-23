import { useLocalSearchParams } from 'expo-router';
import { RecipeDetailScreen } from '@/screens/RecipeDetailScreen';

export default function RecipeDetailRoute() {
  const { id, returnTo, returnId } = useLocalSearchParams<{ id: string; returnTo?: string; returnId?: string }>();
  return <RecipeDetailScreen recipeId={parseInt(id, 10)} returnTo={returnTo} returnId={returnId} />;
}
