import { EmptyState } from '@/components/EmptyState';
import { View, StyleSheet } from 'react-native';
import { COLORS } from '@/utils/theme';

export default function CatalogRoute() {
  return (
    <View style={styles.container}>
      <EmptyState
        icon="clipboard-list-outline"
        message="El catálogo de productos e ingredientes estará disponible próximamente."
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.background },
});
