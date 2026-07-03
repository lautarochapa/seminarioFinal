import type { PaginatedLinks, PaginatedMeta, PaginatedResponse } from '@/types/api';
import type { RecipeSuggestion, RecipeSummary } from '@/types/recipe';

interface RawRecipeSuggestionFlat extends Record<string, unknown> {
  id: unknown;
  name: unknown;
}

export interface RecipeSuggestionsNormalizationResult {
  response: PaginatedResponse<RecipeSuggestion>;
  invalidCount: number;
}

const EMPTY_META: PaginatedMeta = {
  current_page: 1,
  per_page: 20,
  total: 0,
  last_page: 1,
};

const EMPTY_LINKS: PaginatedLinks = {
  first: null,
  last: null,
  prev: null,
  next: null,
};

export function normalizeRecipeSuggestions(payload: unknown): RecipeSuggestionsNormalizationResult {
  const container = readContainer(payload);
  const rawItems = readItems(container);
  let invalidCount = 0;

  const data = rawItems.reduce<RecipeSuggestion[]>((valid, item) => {
    const normalized = normalizeSuggestionItem(item);
    if (normalized) {
      valid.push(normalized);
    } else {
      invalidCount += 1;
    }
    return valid;
  }, []);

  if (invalidCount > 0 && isDevelopment()) {
    console.warn(`[RecipeSuggestions] discarded ${invalidCount} invalid item(s) from API response.`);
  }

  return {
    response: {
      data,
      meta: readMeta(container, data.length),
      links: readLinks(container),
      trace_id: readStringField(container, 'trace_id') ?? '',
    },
    invalidCount,
  };
}

function readContainer(payload: unknown): Record<string, unknown> {
  if (!isRecord(payload)) return {};
  if (isRecord(payload.data) && Array.isArray(payload.data.data)) return payload.data;
  return payload;
}

function readItems(container: Record<string, unknown>): unknown[] {
  if (Array.isArray(container.data)) return container.data;
  if (Array.isArray(container.suggestions)) return container.suggestions;
  if (Array.isArray(container.recipes)) return container.recipes;
  return [];
}

function normalizeSuggestionItem(item: unknown): RecipeSuggestion | null {
  if (!isRecord(item)) return null;

  const nested = readNestedRecipe(item);
  if (nested) {
    return {
      recipe: nested,
      reason: readReason(item),
      score: readNumberField(item, 'score'),
      missing_ingredients_count: readNumberField(item, 'missing_ingredients_count'),
      available_ingredients_count: readNumberField(item, 'available_ingredients_count'),
    };
  }

  const flat = readFlatRecipe(item);
  if (!flat) return null;

  return {
    recipe: flat,
    reason: readReason(item),
    score: readNumberField(item, 'score'),
    missing_ingredients_count: readNumberField(item, 'missing_ingredients_count'),
    available_ingredients_count: readNumberField(item, 'available_ingredients_count'),
  };
}

function readNestedRecipe(item: Record<string, unknown>): RecipeSummary | null {
  if (isRecord(item.recipe)) return readFlatRecipe(item.recipe);
  if (isRecord(item.suggestion) && isRecord(item.suggestion.recipe)) {
    return readFlatRecipe(item.suggestion.recipe);
  }
  return null;
}

function readFlatRecipe(item: unknown): RecipeSummary | null {
  if (!isRecord(item)) return null;
  const raw = item as RawRecipeSuggestionFlat;
  const id = readNumber(raw.id);
  const name = typeof raw.name === 'string' ? raw.name : null;
  if (id === null || !name) return null;

  return {
    id,
    name,
    difficulty: readStringField(item, 'difficulty'),
    servings: readNumberField(item, 'servings'),
    prep_time_minutes: readNumberField(item, 'prep_time_minutes'),
    cook_time_minutes: readNumberField(item, 'cook_time_minutes'),
    is_official: readBooleanField(item, 'is_official') ?? undefined,
    status: readStringField(item, 'status') ?? undefined,
  };
}

function readReason(item: Record<string, unknown>): string | null {
  const reason = readStringField(item, 'reason');
  if (reason) return reason;
  if (Array.isArray(item.reasons) && typeof item.reasons[0] === 'string') return item.reasons[0];
  return null;
}

function readMeta(container: Record<string, unknown>, total: number): PaginatedMeta {
  if (!isRecord(container.meta)) return { ...EMPTY_META, total };
  return {
    current_page: readNumber(container.meta.current_page) ?? EMPTY_META.current_page,
    per_page: readNumber(container.meta.per_page) ?? EMPTY_META.per_page,
    total: readNumber(container.meta.total) ?? total,
    last_page: readNumber(container.meta.last_page) ?? EMPTY_META.last_page,
  };
}

function readLinks(container: Record<string, unknown>): PaginatedLinks {
  if (!isRecord(container.links)) return EMPTY_LINKS;
  return {
    first: readStringField(container.links, 'first'),
    last: readStringField(container.links, 'last'),
    prev: readStringField(container.links, 'prev'),
    next: readStringField(container.links, 'next'),
  };
}

function readNumberField(item: unknown, field: string): number | null {
  return isRecord(item) ? readNumber(item[field]) : null;
}

function readStringField(item: unknown, field: string): string | null {
  if (!isRecord(item)) return null;
  return typeof item[field] === 'string' ? item[field] : null;
}

function readBooleanField(item: unknown, field: string): boolean | null {
  if (!isRecord(item)) return null;
  return typeof item[field] === 'boolean' ? item[field] : null;
}

function readNumber(value: unknown): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) return value;
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }
  return null;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}

function isDevelopment(): boolean {
  return typeof __DEV__ !== 'undefined' ? __DEV__ : process.env.NODE_ENV !== 'production';
}
