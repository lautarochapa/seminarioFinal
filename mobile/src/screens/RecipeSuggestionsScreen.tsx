import React, { useCallback, useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect, useRouter } from 'expo-router';
import { AppHeader } from '@/components/AppHeader';
import { AppButton } from '@/components/AppButton';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { FamilyGroupSelector } from '@/components/FamilyGroupSelector';
import { LoadingScreen } from '@/components/LoadingScreen';
import { RecipeCard } from '@/components/RecipeCard';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { recipeSuggestionsApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { goBackOrHome } from '@/utils/navigation';
import { COLORS, FONT_SIZE, SPACING } from '@/utils/theme';
import type { RecipeSuggestion } from '@/types/recipe';

export function RecipeSuggestionsScreen() {
  const router = useRouter(); const { selectedGroup } = useFamilyGroupContext();
  const [available, setAvailable] = useState<RecipeSuggestion[]>([]); const [almost, setAlmost] = useState<RecipeSuggestion[]>([]);
  const [loading, setLoading] = useState(false); const [error, setError] = useState<ApiError | null>(null);
  const refresh = useCallback(async () => {
    if (!selectedGroup) { setAvailable([]); setAlmost([]); return; }
    setLoading(true); setError(null);
    try { const [now, close] = await Promise.all([recipeSuggestionsApi.available(selectedGroup.id), recipeSuggestionsApi.almostAvailable(selectedGroup.id)]); setAvailable(now.data); setAlmost(close.data); }
    catch (err) { if (err instanceof ApiError) setError(err); }
    finally { setLoading(false); }
  }, [selectedGroup]);
  useFocusEffect(useCallback(() => { void refresh(); }, [refresh]));
  const open = (item: RecipeSuggestion) => router.push({ pathname: '/(app)/recipes/[id]' as never, params: { id: String(item.recipe.id) } });
  return <View style={styles.fill}><AppHeader title="Qué puedo cocinar hoy" showBack onBack={goBackOrHome} /><FamilyGroupSelector />
    {loading ? <LoadingScreen message="Revisando tu stock..." /> : error ? <ErrorState message={error.normalized.message} traceId={error.normalized.traceId} onRetry={refresh} type="server" /> : !selectedGroup ? <EmptyState icon="account-group-outline" message="Seleccioná un grupo familiar." /> :
      <ScrollView contentContainerStyle={styles.list}>
        <Text style={styles.heading}>Para cocinar ahora</Text>
        {available.length ? available.map((item) => <View key={`a-${item.recipe.id}`}><RecipeCard recipe={item.recipe} badge="Tenés todos los ingredientes" onPress={() => open(item)} /><AppButton title="Cocinar" onPress={() => open(item)} /></View>) : <EmptyState icon="chef-hat" message="Todavía no encontramos recetas que puedas preparar con tu stock." actionTitle="Agregar productos" onAction={() => router.push('/(app)/stock/create' as never)} />}
        <Text style={styles.heading}>Te falta poco</Text>
        {almost.length ? almost.map((item) => <RecipeCard key={`m-${item.recipe.id}`} recipe={item.recipe} badge={`Te faltan ${item.missing_ingredients_count ?? 1} ingredientes`} onPress={() => open(item)} />) : <Text style={styles.muted}>No hay recetas con pocos faltantes.</Text>}
      </ScrollView>}
  </View>;
}
const styles = StyleSheet.create({ fill: { flex: 1, backgroundColor: COLORS.background }, list: { padding: SPACING.md, gap: SPACING.md, paddingBottom: SPACING.xxl }, heading: { color: COLORS.textPrimary, fontSize: FONT_SIZE.lg, fontWeight: '800' }, muted: { color: COLORS.textSecondary } });
