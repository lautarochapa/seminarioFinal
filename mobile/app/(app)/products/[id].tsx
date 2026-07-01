import { useLocalSearchParams } from 'expo-router';
import { ProductDetailScreen } from '@/screens/ProductDetailScreen';

export default function ProductDetailRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <ProductDetailScreen productId={parseInt(id, 10)} />;
}
