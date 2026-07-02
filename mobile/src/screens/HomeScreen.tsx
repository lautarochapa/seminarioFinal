import React from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '@/auth/AuthContext';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { AppLogo } from '@/components/AppLogo';
import { FeatureCard } from '@/components/FeatureCard';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';

export function HomeScreen() {
  const { user } = useAuth();
  const { selectedGroup } = useFamilyGroupContext();
  const router = useRouter();
  const insets = useSafeAreaInsets();

  const displayName = user
    ? (user.name || user.lastname ? `${user.name} ${user.lastname}`.trim() : user.email)
    : '';

  const initials = user
    ? `${(user.name || '?').charAt(0)}${(user.lastname || '').charAt(0)}`.toUpperCase()
    : '?';

  return (
    <ScrollView
      style={styles.scroll}
      contentContainerStyle={styles.content}
      showsVerticalScrollIndicator={false}
    >
      {/* Dark brand header — paddingTop adapts to safe-area top inset */}
      <View style={[styles.header, { paddingTop: insets.top + SPACING.sm }]}>
        <View style={styles.headerTop}>
          <AppLogo variant="small" inverted />
          <View style={styles.avatar}>
            <Text style={styles.avatarText}>{initials}</Text>
          </View>
        </View>

        <Text style={styles.greeting}>
          Hola, <Text style={styles.greetingName}>{displayName}</Text>
        </Text>

        {selectedGroup ? (
          <View style={styles.groupChip}>
            <Text style={styles.groupChipText} numberOfLines={1}>
              {selectedGroup.name}
            </Text>
          </View>
        ) : null}
      </View>

      {/* Feature list */}
      <View style={styles.featureList}>
        <Text style={styles.sectionTitle}>Mi hogar</Text>
        <FeatureCard
          icon="account-circle-outline"
          title="Mi perfil"
          subtitle="Datos personales, objetivos y preferencias"
          onPress={() => router.navigate('/(app)/profile' as never)}
          iconBg={COLORS.primarySurface}
          iconColor={COLORS.primary}
        />
        <FeatureCard
          icon="account-group-outline"
          title="Grupos familiares"
          subtitle="Familia y miembros del hogar"
          onPress={() => router.push('/(app)/groups' as never)}
          iconBg={COLORS.infoLight}
          iconColor={COLORS.info}
        />
        <Text style={styles.sectionTitle}>Cocina</Text>
        <FeatureCard
          icon="book-open-outline"
          title="Catálogo"
          subtitle="Productos e ingredientes disponibles"
          onPress={() => router.navigate('/(app)/catalog' as never)}
          iconBg={COLORS.successLight}
          iconColor={COLORS.success}
        />
        <FeatureCard
          icon="silverware-fork-knife"
          title="Recetas"
          subtitle="Buscar, guardar y cocinar recetas"
          onPress={() => router.push('/(app)/recipes' as never)}
          iconBg={COLORS.primarySurface}
          iconColor={COLORS.primary}
        />
        <FeatureCard
          icon="heart-outline"
          title="Favoritos"
          subtitle="Tus recetas guardadas"
          onPress={() => router.push('/(app)/recipe-favorites' as never)}
          iconBg={COLORS.errorLight}
          iconColor={COLORS.error}
        />
        <FeatureCard
          icon="lightbulb-outline"
          title="Sugerencias"
          subtitle="Ideas segun tu hogar"
          onPress={() => router.push('/(app)/recipe-suggestions' as never)}
          iconBg={COLORS.infoLight}
          iconColor={COLORS.info}
        />
        <FeatureCard
          icon="calendar-month-outline"
          title="Planificacion"
          subtitle="Semana, comidas y meal plans"
          onPress={() => router.push('/(app)/planning' as never)}
          iconBg={COLORS.warningLight}
          iconColor={COLORS.warning}
        />
        <FeatureCard
          icon="calendar-text-outline"
          title="Meal plans"
          subtitle="Planes familiares y aprobados"
          onPress={() => router.push('/(app)/meal-plans' as never)}
          iconBg={COLORS.successLight}
          iconColor={COLORS.primaryDark}
        />
        <Text style={styles.sectionTitle}>Compras</Text>
        <FeatureCard
          icon="package-variant-closed"
          title="Stock del hogar"
          subtitle="Control de inventario y vencimientos"
          onPress={() => router.navigate('/(app)/stock' as never)}
          iconBg={COLORS.warningLight}
          iconColor={COLORS.warning}
        />
        <FeatureCard
          icon="cart-outline"
          title="Listas de compras"
          subtitle="Organizá las compras del grupo"
          onPress={() => router.push('/(app)/shopping-lists' as never)}
          iconBg={COLORS.primarySurface}
          iconColor={COLORS.primaryDark}
        />
        <FeatureCard
          icon="receipt"
          title="Historial de compras"
          subtitle="Compras realizadas y totales"
          onPress={() => router.push('/(app)/purchases' as never)}
          iconBg={COLORS.infoLight}
          iconColor={COLORS.info}
        />
        <FeatureCard
          icon="cash-multiple"
          title="Presupuesto"
          subtitle="Control de gastos y proyección"
          onPress={() => router.push('/(app)/budgets' as never)}
          iconBg={COLORS.successLight}
          iconColor={COLORS.primaryDark}
        />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  scroll: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  content: {
    flexGrow: 1,
    paddingBottom: SPACING.xxl,
  },
  header: {
    backgroundColor: COLORS.dark,
    // paddingTop is set inline via insets.top + SPACING.sm
    paddingBottom: SPACING.md,
    paddingHorizontal: SPACING.lg,
    borderBottomLeftRadius: RADIUS.xl,
    borderBottomRightRadius: RADIUS.xl,
    gap: SPACING.sm,
    marginBottom: SPACING.md,
  },
  headerTop: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: SPACING.sm,
  },
  avatar: {
    width: 40,
    height: 40,
    borderRadius: RADIUS.full,
    backgroundColor: COLORS.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: {
    fontSize: FONT.labelSize,
    fontWeight: '700',
    color: '#fff',
  },
  greeting: {
    fontSize: FONT.titleSize,
    fontWeight: '400',
    color: 'rgba(255,255,255,0.8)',
  },
  greetingName: {
    fontWeight: FONT.titleWeight,
    color: '#fff',
  },
  groupChip: {
    alignSelf: 'flex-start',
    backgroundColor: 'rgba(4,172,133,0.25)',
    borderRadius: RADIUS.full,
    borderWidth: 1,
    borderColor: COLORS.primaryLight,
    paddingHorizontal: SPACING.sm,
    paddingVertical: 3,
    maxWidth: '80%',
  },
  groupChipText: {
    fontSize: FONT.captionSize,
    color: COLORS.primaryLight,
    fontWeight: '600',
  },
  featureList: {
    paddingHorizontal: SPACING.md,
    gap: SPACING.md,
  },
  sectionTitle: {
    fontSize: FONT.labelSize,
    fontWeight: '800',
    color: COLORS.textSecondary,
    marginTop: SPACING.sm,
    textTransform: 'uppercase',
  },
});
