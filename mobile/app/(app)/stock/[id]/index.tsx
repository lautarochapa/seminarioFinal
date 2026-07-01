import { useLocalSearchParams } from 'expo-router';
import { StockItemDetailScreen } from '@/screens/StockItemDetailScreen';

export default function StockItemDetailRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <StockItemDetailScreen stockItemId={parseInt(id, 10)} />;
}
