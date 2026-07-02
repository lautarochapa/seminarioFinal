import { useLocalSearchParams } from 'expo-router';
import { RecipeDetailScreen } from '@/screens/RecipeDetailScreen';

export default function RecipeDetailRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <RecipeDetailScreen recipeId={parseInt(id, 10)} />;
}
