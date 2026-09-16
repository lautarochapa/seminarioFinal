export type OnboardingStepKey =
  | 'basic_profile'
  | 'objective'
  | 'meals_per_day'
  | 'food_preferences'
  | 'family_group';

export interface OnboardingStep {
  complete: boolean;
  optional?: boolean;
  missing?: string[];
  has_target_weight?: boolean;
  objectives_count?: number;
  value?: number | null;
  restrictions_count?: number;
  allergies_count?: number;
  health_conditions_count?: number;
  groups_count?: number;
}

export interface OnboardingStatus {
  complete: boolean;
  next_step: OnboardingStepKey | null;
  required_steps: OnboardingStepKey[];
  completed_count: number;
  steps: Record<OnboardingStepKey, OnboardingStep>;
}
