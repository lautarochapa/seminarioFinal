import { useLocalSearchParams } from 'expo-router';
import { StockCreateScreen } from '@/screens/StockCreateScreen';

export default function StockCreateRoute() {
  const { product_id, product_name } = useLocalSearchParams<{
    product_id?: string;
    product_name?: string;
  }>();

  const prefilledProductId = product_id ? parseInt(product_id, 10) : undefined;
  const prefilledProductName = product_name ?? undefined;

  return (
    <StockCreateScreen
      prefilledProductId={isNaN(prefilledProductId ?? NaN) ? undefined : prefilledProductId}
      prefilledProductName={prefilledProductName}
    />
  );
}
