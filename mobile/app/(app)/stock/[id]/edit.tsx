import { useLocalSearchParams } from 'expo-router';
import { StockItemEditScreen } from '@/screens/StockItemEditScreen';

export default function StockItemEditRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <StockItemEditScreen stockItemId={parseInt(id, 10)} />;
}
