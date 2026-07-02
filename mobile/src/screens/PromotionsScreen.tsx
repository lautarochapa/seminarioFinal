import React, { useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { AppHeader } from '@/components/AppHeader';
import { BranchCard } from '@/components/BranchCard';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { LoadingScreen } from '@/components/LoadingScreen';
import { PromotionCard } from '@/components/PromotionCard';
import { useBranches } from '@/hooks/useBranches';
import { usePromotions } from '@/hooks/usePromotions';
import { friendlyMessage } from '@/utils/errorParser';
import { goBackOrHome } from '@/utils/navigation';
import { COLORS, FONT, SPACING } from '@/utils/theme';
import type { SupermarketBranch } from '@/types/retail';

export function PromotionsScreen() {
  const [branch, setBranch] = useState<SupermarketBranch | null>(null);
  const branches = useBranches();
  const promotions = usePromotions(branch?.id ?? null);

  return (
    <View style={styles.fill}>
      <AppHeader title="Promociones" subtitle={branch?.name || 'Elegir sucursal'} showBack onBack={goBackOrHome} />
      {!branch ? (
        <FlatList
          data={branches.data}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => <BranchCard item={item} onPress={() => setBranch(item)} />}
          ListHeaderComponent={<Text style={styles.note}>La API publica expone promociones por sucursal.</Text>}
          ListEmptyComponent={branches.loading ? null : <EmptyState icon="ticket-percent-outline" message="No hay sucursales para consultar promociones." />}
          contentContainerStyle={styles.list}
        />
      ) : (
        <View style={styles.content}>
          <Pressable onPress={() => setBranch(null)}><Text style={styles.link}>Cambiar sucursal</Text></Pressable>
          {promotions.loading ? <LoadingScreen message="Cargando promociones..." /> : null}
          {promotions.error ? <ErrorState message={friendlyMessage(promotions.error)} traceId={promotions.error.traceId} onRetry={promotions.refresh} type="server" /> : null}
          {!promotions.loading && promotions.data.length === 0 ? <EmptyState icon="ticket-percent-outline" message="No hay promociones para esta sucursal." /> : null}
          {promotions.data.map((item) => <PromotionCard key={item.id} item={item} />)}
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  list: { padding: SPACING.md, gap: SPACING.md },
  content: { flex: 1, padding: SPACING.md, gap: SPACING.md },
  note: { color: COLORS.textSecondary, fontSize: FONT.captionSize },
  link: { color: COLORS.primary, fontSize: FONT.bodySize, fontWeight: '800' },
});
