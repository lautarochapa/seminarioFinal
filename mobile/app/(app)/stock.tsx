import { EmptyState } from '@/components/EmptyState';
import { View, StyleSheet } from 'react-native';
import { COLORS } from '@/utils/theme';

export default function StockRoute() {
  return (
    <View style={styles.container}>
      <EmptyState
        icon="package-variant-closed"
        message="El control de stock de tu hogar estará disponible próximamente."
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.background },
});
