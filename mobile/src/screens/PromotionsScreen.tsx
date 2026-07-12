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
import { useGlobalPromotions } from '@/hooks/useGlobalPromotions';
import { friendlyMessage } from '@/utils/errorParser';
import { goBackOrHome } from '@/utils/navigation';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';
import type { SupermarketBranch } from '@/types/retail';

type Mode = 'all' | 'branch';

export function PromotionsScreen() {
  const [mode, setMode] = useState<Mode>('all');
  const [branch, setBranch] = useState<SupermarketBranch | null>(null);
  const branches = useBranches();
  const byBranch = usePromotions(branch?.id ?? null);
  const global = useGlobalPromotions();

  const active = mode === 'all' ? global : byBranch;
  const list = mode === 'all' ? global.data : byBranch.data;

  return (
    <View style={styles.fill}>
      <AppHeader title="Promociones" subtitle={mode === 'branch' && branch ? branch.name : 'Todas las promociones vigentes'} showBack onBack={goBackOrHome} />
      <View style={styles.tabs}>
        <Pressable
          accessibilityRole="button"
          accessibilityState={{ selected: mode === 'all' }}
          style={[styles.tab, mode === 'all' && styles.tabActive]}
          onPress={() => setMode('all')}
        >
          <Text style={[styles.tabText, mode === 'all' && styles.tabTextActive]}>Todas</Text>
        </Pressable>
        <Pressable
          accessibilityRole="button"
          accessibilityState={{ selected: mode === 'branch' }}
          style={[styles.tab, mode === 'branch' && styles.tabActive]}
          onPress={() => setMode('branch')}
        >
          <Text style={[styles.tabText, mode === 'branch' && styles.tabTextActive]}>Por sucursal</Text>
        </Pressable>
      </View>

      {mode === 'branch' && !branch ? (
        <FlatList
          data={branches.data}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => <BranchCard item={item} onPress={() => setBranch(item)} />}
          ListEmptyComponent={branches.loading ? null : <EmptyState icon="ticket-percent-outline" message="No hay sucursales para consultar promociones." />}
          contentContainerStyle={styles.list}
        />
      ) : (
        <FlatList
          data={list}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => <PromotionCard item={item} />}
          contentContainerStyle={styles.content}
          ListHeaderComponent={
            <View style={styles.headerGap}>
              {mode === 'branch' && branch ? (
                <Pressable onPress={() => setBranch(null)} accessibilityRole="button"><Text style={styles.link}>Cambiar sucursal</Text></Pressable>
              ) : null}
              {active.loading ? <LoadingScreen message="Cargando promociones..." /> : null}
              {active.error ? <ErrorState message={friendlyMessage(active.error)} traceId={active.error.traceId} onRetry={active.refresh} type="server" /> : null}
            </View>
          }
          ListEmptyComponent={!active.loading ? <EmptyState icon="ticket-percent-outline" message="No hay promociones disponibles." /> : null}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  list: { padding: SPACING.md, gap: SPACING.md },
  content: { padding: SPACING.md, gap: SPACING.md },
  headerGap: { gap: SPACING.md, marginBottom: SPACING.md },
  tabs: { flexDirection: 'row', gap: SPACING.sm, paddingHorizontal: SPACING.md, paddingTop: SPACING.sm },
  tab: { flex: 1, paddingVertical: SPACING.sm, borderRadius: RADIUS.md, backgroundColor: COLORS.surface, alignItems: 'center', borderWidth: 1, borderColor: COLORS.border, minHeight: 44, justifyContent: 'center' },
  tabActive: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  tabText: { color: COLORS.textSecondary, fontWeight: '700', fontSize: FONT.captionSize },
  tabTextActive: { color: COLORS.surface },
  note: { color: COLORS.textSecondary, fontSize: FONT.captionSize },
  link: { color: COLORS.primary, fontSize: FONT.bodySize, fontWeight: '800' },
});
