import React from 'react';
import { FlatList, StyleSheet, View } from 'react-native';
import { AppHeader } from '@/components/AppHeader';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { LoadingScreen } from '@/components/LoadingScreen';
import { PaymentMethodCard } from '@/components/PaymentMethodCard';
import { usePaymentMethods } from '@/hooks/usePaymentMethods';
import { friendlyMessage } from '@/utils/errorParser';
import { goBackOrHome } from '@/utils/navigation';
import { COLORS, SPACING } from '@/utils/theme';

export function PaymentMethodsScreen() {
  const { data, loading, error, refresh } = usePaymentMethods();
  return (
    <View style={styles.fill}>
      <AppHeader title="Metodos de pago" showBack onBack={goBackOrHome} />
      {loading ? <LoadingScreen message="Cargando metodos..." /> : null}
      {error ? <ErrorState message={friendlyMessage(error)} traceId={error.traceId} onRetry={refresh} type="server" /> : null}
      {!loading && !error ? (
        <FlatList
          data={data}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => <PaymentMethodCard item={item} />}
          ListEmptyComponent={<EmptyState icon="credit-card-off-outline" message="No hay metodos de pago disponibles." />}
          contentContainerStyle={styles.list}
        />
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  list: { padding: SPACING.md, gap: SPACING.md },
});
