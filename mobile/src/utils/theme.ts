// Brand colors derived from CocinaComidaControl web
// Primary: #04AC85 (teal-green, taken from navbar.css and login.css)
// Dark:    #24252A (navbar background)

export const COLORS = {
  // Brand teal
  primary: '#04AC85',
  primaryDark: '#037A60',
  primaryLight: '#33BFAA',
  primarySurface: '#E8F7F3',

  // Neutrals
  dark: '#24252A',
  background: '#F4F6F5',
  surface: '#FFFFFF',
  surfaceElevated: '#FAFBFA',
  border: '#E2E8E6',
  borderLight: '#EEF2F0',

  // Text
  textPrimary: '#1C2422',
  textSecondary: '#5C6B67',
  textHint: '#9AABA7',
  textInverse: '#FFFFFF',

  // Semantic
  success: '#04AC85',
  successLight: '#E8F7F3',
  warning: '#D97706',
  warningLight: '#FEF3C7',
  error: '#DC2626',
  errorLight: '#FEE2E2',
  info: '#0284C7',
  infoLight: '#E0F2FE',

  // UI
  disabled: '#C4D0CC',
  overlay: 'rgba(28, 36, 34, 0.55)',
  skeleton: '#E8EEEC',
} as const;

export const SPACING = {
  xs: 4,
  sm: 8,
  md: 16,
  lg: 24,
  xl: 32,
  xxl: 48,
  xxxl: 64,
} as const;

export const FONT = {
  // Display (hero / splash)
  displaySize: 32,
  displayWeight: '800' as const,
  displayLineHeight: 40,

  // Title (screen heading)
  titleSize: 22,
  titleWeight: '700' as const,
  titleLineHeight: 28,

  // Subtitle / card heading
  subtitleSize: 17,
  subtitleWeight: '600' as const,
  subtitleLineHeight: 22,

  // Body
  bodySize: 15,
  bodyWeight: '400' as const,
  bodyLineHeight: 22,

  // Caption
  captionSize: 12,
  captionWeight: '400' as const,
  captionLineHeight: 16,

  // Button
  buttonSize: 15,
  buttonWeight: '600' as const,
  buttonLineHeight: 20,

  // Label / form
  labelSize: 13,
  labelWeight: '500' as const,
  labelLineHeight: 18,
} as const;

// Keep FONT_SIZE alias for backward compat with existing screens
export const FONT_SIZE = {
  xs: 12,
  sm: 14,
  md: 16,
  lg: 18,
  xl: 22,
  xxl: 28,
} as const;

export const RADIUS = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 24,
  full: 999,
} as const;

export const SHADOW = {
  sm: {
    shadowColor: '#1C2422',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.06,
    shadowRadius: 3,
    elevation: 2,
  },
  md: {
    shadowColor: '#1C2422',
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.09,
    shadowRadius: 8,
    elevation: 4,
  },
  lg: {
    shadowColor: '#1C2422',
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.12,
    shadowRadius: 16,
    elevation: 8,
  },
} as const;

export const TOUCH_TARGET = 48;
