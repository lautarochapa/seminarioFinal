import React, { useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { AppHeader } from '@/components/AppHeader';
import { AppButton } from '@/components/AppButton';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { EstimatedPriceRow } from '@/components/EstimatedPriceRow';
import { LoadingScreen } from '@/components/LoadingScreen';
import { ProductMappingWarning } from '@/components/ProductMappingWarning';
import { RecipeIngredientRow } from '@/components/RecipeIngredientRow';
import { RecipeStep } from '@/components/RecipeStep';
import { FavoriteButton } from '@/components/FavoriteButton';
import { ShoppingGenerationSummary } from '@/components/ShoppingGenerationSummary';
import { SupermarketSelector } from '@/components/SupermarketSelector';
import { BranchSelector } from '@/components/BranchSelector';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { useRecipeDetail } from '@/hooks/useRecipeDetail';
import { useRecipeFavorites } from '@/hooks/useRecipeFavorites';
import { recipeShoppingListApi, recipesApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import type { RecipeShoppingListResult } from '@/types/recipe';

export function RecipeDetailScreen({ recipeId }: { recipeId: number }) {
  const router = useRouter();
  const { selectedGroup } = useFamilyGroupContext();
  const groupId = selectedGroup?.id ?? null;
  const { data, nutrition, cost, loading, error, refresh } = useRecipeDetail(recipeId);
  const favorites = useRecipeFavorites();
  const [generating, setGenerating] = useState(false);
  const [result, setResult] = useState<RecipeShoppingListResult | null>(null);
  const [cooking, setCooking] = useState(false);
  const [selectedChainId, setSelectedChainId] = useState<number | null>(null);
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(null);

  function handleSelectChain(chainId: number | null) {
    setSelectedChainId(chainId);
    setSelectedBranchId(null);
  }

  async function handleGenerateList() {
    if (!groupId) return;
    setGenerating(true);
    setResult(null);
    try {
      const res = await recipeShoppingListApi.generate(groupId, recipeId, {
        supermarket_chain_id: selectedChainId ?? undefined,
        supermarket_branch_id: selectedBranchId ?? undefined,
      });
      setResult(res.data);
    } catch (err) {
      const msg = err instanceof ApiError ? err.normalized.message : 'No se pudo generar la lista desde la receta.';
      Alert.alert('Error', msg);
    } finally {
      setGenerating(false);
    }
  }

  function handleOpenList() {
    if (!result) return;
    router.push({ pathname: '/(app)/shopping-lists/[id]' as never, params: { id: String(result.shopping_list.id) } });
  }

  function handleCookRecipe() {
    if (!groupId || !data) return;
    const servings = data.servings || 1;
    Alert.alert(
      'Cocinar receta',
      'Se descontaran los ingredientes requeridos del stock del grupo seleccionado.',
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Confirmar',
          style: 'default',
          onPress: () => {
            const key = `${recipeId}-${groupId}-${Date.now()}`;
            setCooking(true);
            recipesApi.cook(recipeId, {
              servings,
              family_group_id: groupId,
              deduct_stock: true,
              idempotency_key: key,
            })
              .then(() => {
                Alert.alert('Receta cocinada', 'El stock fue actualizado.');
                refresh();
              })
              .catch((err) => {
                const msg = err instanceof ApiError ? err.normalized.message : 'No se pudo cocinar la receta.';
                Alert.alert('No se pudo cocinar', msg);
              })
              .finally(() => setCooking(false));
          },
        },
      ],
    );
  }

  if (loading) return <LoadingScreen message="Cargando receta..." />;
  if (error) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Receta" showBack onBack={goBackOrHome} />
        <ErrorState message={friendlyMessage(error)} traceId={error.traceId} onRetry={refresh} type="server" />
      </View>
    );
  }
  if (!data) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Receta" showBack onBack={goBackOrHome} />
        <EmptyState icon="chef-hat" message="No se encontró la receta." />
      </View>
    );
  }

  const minutes = (data.prep_time_minutes ?? 0) + (data.cook_time_minutes ?? 0);
  const listItems = result?.shopping_list.items ?? [];
  const nutritionRows = [
    ['Calorías', nutrition?.calories_per_serving ?? nutrition?.calories_total, 'kcal'],
    ['Proteínas', nutrition?.protein_per_serving ?? nutrition?.protein_total, 'g'],
    ['Carbohidratos', nutrition?.carbohydrates_per_serving ?? nutrition?.carbohydrates_total, 'g'],
    ['Grasas', nutrition?.fat_per_serving ?? nutrition?.fat_total, 'g'],
    ['Sodio', nutrition?.sodium_per_serving ?? nutrition?.sodium_total, 'mg'],
  ].filter((row) => row[1] !== null && row[1] !== undefined && row[1] !== '');
  const costValue = cost?.cost_per_serving ?? cost?.total_cost;

  return (
    <View style={styles.fill}>
      <AppHeader
        title="Receta"
        showBack
        onBack={goBackOrHome}
        rightAction={<FavoriteButton active={favorites.favoriteIds.has(data.id)} loading={Boolean(favorites.savingIds[data.id])} onPress={() => favorites.toggle(data.id).catch(() => undefined)} />}
      />
      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.hero}>
          <Text style={styles.title}>{data.name}</Text>
          {data.description ? <Text style={styles.desc}>{data.description}</Text> : null}
          <View style={styles.metaRow}>
            {minutes > 0 ? <Text style={styles.meta}>{minutes} min</Text> : null}
            {data.servings ? <Text style={styles.meta}>{data.servings} porciones</Text> : null}
            {data.category?.name ? <Text style={styles.meta}>{data.category.name}</Text> : null}
          </View>
          {data.tags && data.tags.length > 0 ? <Text style={styles.tags}>{data.tags.map((t) => t.name).join(' · ')}</Text> : null}
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Ingredientes</Text>
          {data.ingredients && data.ingredients.length > 0 ? data.ingredients.map((item) => <RecipeIngredientRow key={item.id} item={item} />) : <Text style={styles.emptyText}>Sin ingredientes cargados.</Text>}
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Pasos</Text>
          {data.steps && data.steps.length > 0 ? data.steps.map((step) => <RecipeStep key={step.step_number} step={step} />) : <Text style={styles.emptyText}>Sin pasos cargados.</Text>}
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Nutrición y costo</Text>
          {nutritionRows.length > 0 ? nutritionRows.map(([label, value, unit]) => (
            <View key={String(label)} style={styles.infoRow}>
              <Text style={styles.infoLabel}>{label}</Text>
              <Text style={styles.infoValue}>{formatValue(value)} {unit}</Text>
            </View>
          )) : <Text style={styles.emptyText}>Sin información nutricional disponible.</Text>}
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Costo estimado</Text>
            <Text style={styles.infoValue}>{costValue === null || costValue === undefined ? 'Sin información' : `${formatMoneyValue(costValue)}${cost?.currency ? ` ${cost.currency}` : ''}`}</Text>
          </View>
        </View>

        {groupId ? (
          <>
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Cocinar</Text>
              <Text style={styles.hint}>Desconta los ingredientes del stock del grupo seleccionado.</Text>
              <AppButton
                title={cooking ? 'Cocinando...' : 'Marcar como cocinada'}
                onPress={handleCookRecipe}
                loading={cooking}
                fullWidth
              />
            </View>

            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Generar lista de compras</Text>
              <Text style={styles.hint}>Elegí un supermercado (opcional) para estimar precios más precisos.</Text>
              <SupermarketSelector selectedChainId={selectedChainId} onSelect={handleSelectChain} />
              {selectedChainId ? (
                <BranchSelector chainId={selectedChainId} selectedBranchId={selectedBranchId} onSelect={setSelectedBranchId} />
              ) : null}
              <AppButton
                title={generating ? 'Generando...' : 'Generar lista de compras'}
                onPress={handleGenerateList}
                loading={generating}
                fullWidth
                style={styles.generateBtn}
              />
            </View>

            {result ? (
              <>
                <ShoppingGenerationSummary
                  itemsAdded={result.items_added}
                  estimatedTotal={result.estimated_total}
                  itemsWithoutPrice={result.items_without_price}
                  itemsUnmapped={result.unmapped_ingredients.length}
                />

                {result.priced_items.length > 0 ? (
                  <View style={styles.section}>
                    <Text style={styles.sectionTitle}>Items agregados</Text>
                    {result.priced_items.map((priced) => {
                      const listItem = listItems.find((li) => li.id === priced.shopping_list_item_id);
                      const name = listItem?.product?.name ?? listItem?.ingredient?.name ?? 'Item';
                      return <EstimatedPriceRow key={priced.shopping_list_item_id} item={priced} name={name} unitSymbol={listItem?.unit?.symbol} />;
                    })}
                  </View>
                ) : null}

                {result.substitutions.length > 0 ? (
                  <View style={styles.section}>
                    <Text style={styles.sectionTitle}>Sustituciones</Text>
                    {result.substitutions.map((sub, idx) => (
                      <Text key={`${sub.original_ingredient_id}-${idx}`} style={styles.hint}>
                        Se usará {sub.resolved_ingredient_name ?? 'un sustituto'} como reemplazo
                        {sub.reason ? ` (${sub.reason})` : ''}.
                      </Text>
                    ))}
                  </View>
                ) : null}

                {result.unmapped_ingredients.length > 0 ? (
                  <View style={styles.section}>
                    <Text style={styles.sectionTitle}>No mapeados</Text>
                    {result.unmapped_ingredients.map((item, idx) => (
                      <ProductMappingWarning key={`${item.ingredient_id}-${idx}`} item={item} />
                    ))}
                  </View>
                ) : null}

                <AppButton title="Abrir lista" onPress={handleOpenList} fullWidth />
              </>
            ) : null}
          </>
        ) : (
          <View style={styles.noGroup}>
            <Text style={styles.blocked}>Seleccioná un grupo familiar para generar una lista desde esta receta.</Text>
            <AppButton title="Seleccionar grupo" variant="outline" onPress={() => router.push('/(app)/groups' as never)} />
          </View>
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  content: { padding: SPACING.md, gap: SPACING.md, paddingBottom: SPACING.xxl },
  hero: { backgroundColor: COLORS.surface, borderRadius: RADIUS.sm, padding: SPACING.md, gap: SPACING.sm, ...SHADOW.sm },
  title: { fontSize: FONT.titleSize, fontWeight: '800', color: COLORS.textPrimary },
  desc: { fontSize: FONT.bodySize, color: COLORS.textSecondary, lineHeight: FONT.bodyLineHeight },
  metaRow: { flexDirection: 'row', flexWrap: 'wrap', gap: SPACING.sm },
  meta: { fontSize: FONT.captionSize, color: COLORS.textSecondary, fontWeight: '600' },
  tags: { fontSize: FONT.captionSize, color: COLORS.primaryDark },
  section: { backgroundColor: COLORS.surface, borderRadius: RADIUS.sm, padding: SPACING.md, gap: SPACING.sm, ...SHADOW.sm },
  sectionTitle: { fontSize: FONT.subtitleSize, fontWeight: '700', color: COLORS.textPrimary },
  hint: { fontSize: FONT.captionSize, color: COLORS.textSecondary },
  emptyText: { color: COLORS.textHint },
  blocked: { fontSize: FONT.captionSize, color: COLORS.textSecondary, textAlign: 'center' },
  noGroup: { backgroundColor: COLORS.surface, borderRadius: RADIUS.sm, padding: SPACING.md, gap: SPACING.sm, alignItems: 'center', ...SHADOW.sm },
  generateBtn: { marginTop: SPACING.xs },
  infoRow: { flexDirection: 'row', justifyContent: 'space-between', gap: SPACING.sm },
  infoLabel: { color: COLORS.textSecondary, fontSize: FONT.captionSize },
  infoValue: { color: COLORS.textPrimary, fontSize: FONT.captionSize, fontWeight: '700' },
});

function formatValue(value: unknown): string {
  const n = Number(value);
  if (!Number.isFinite(n)) return String(value ?? '-');
  return n.toLocaleString('es-AR', { maximumFractionDigits: 2 });
}

function formatMoneyValue(value: unknown): string {
  const n = Number(value);
  if (!Number.isFinite(n)) return String(value ?? '-');
  return n.toLocaleString('es-AR', { style: 'currency', currency: 'ARS' });
}
