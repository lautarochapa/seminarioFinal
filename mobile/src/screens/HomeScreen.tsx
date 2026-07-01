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
        <FeatureCard
          icon="account-circle-outline"
          title="Mi perfil"
          subtitle="Datos personales, objetivos y preferencias"
          onPress={() => router.navigate('/(app)/profile')}
          iconBg={COLORS.primarySurface}
          iconColor={COLORS.primary}
        />
        <FeatureCard
          icon="account-group-outline"
          title="Grupos familiares"
          subtitle="Familia y miembros del hogar"
          onPress={() => router.push('/(app)/groups')}
          iconBg={COLORS.infoLight}
          iconColor={COLORS.info}
        />
        <FeatureCard
          icon="book-open-outline"
          title="Catálogo"
          subtitle="Productos e ingredientes disponibles"
          onPress={() => router.navigate('/(app)/catalog')}
          iconBg={COLORS.successLight}
          iconColor={COLORS.success}
        />
        <FeatureCard
          icon="package-variant-closed"
          title="Stock del hogar"
          subtitle="Control de inventario y vencimientos"
          onPress={() => router.navigate('/(app)/stock')}
          iconBg={COLORS.warningLight}
          iconColor={COLORS.warning}
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
});
