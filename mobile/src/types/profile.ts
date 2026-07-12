export interface ProfilePreferences {
  uses_app_for_health: boolean;
  uses_app_for_budget: boolean;
  uses_app_for_organization: boolean;
}

export interface ProfileObjective {
  id: number;
  code: string;
  name: string;
}

export interface Profile {
  id: number;
  name: string;
  lastname: string;
  email: string;
  phone: string | null;
  birth_date: string | null;
  gender: string | null;
  height_cm: number | null;
  current_weight_kg: number | null;
  target_weight_kg: number | null;
  activity_level: string | null;
  meals_per_day: number | null;
  notes: string | null;
  objectives: ProfileObjective[];
  preferences: ProfilePreferences;
}

export interface ProfileUpdateRequest {
  name?: string;
  lastname?: string;
  phone?: string | null;
  notes?: string | null;
  preferences?: Partial<ProfilePreferences>;
  birth_date?: string | null;
  gender?: string | null;
  height_cm?: number | null;
  current_weight_kg?: number | null;
  target_weight_kg?: number | null;
  activity_level?: string | null;
  meals_per_day?: number | null;
  objective_ids?: number[];
}

export type HealthPreferenceType = 'dietary-restrictions' | 'health-conditions' | 'allergies';
export interface HealthPreferenceCatalogItem { id: number; code: string; name: string; description: string | null }
export interface UserHealthPreference { id: number; item: HealthPreferenceCatalogItem; severity: string | null; notes: string | null; created_at: string }
