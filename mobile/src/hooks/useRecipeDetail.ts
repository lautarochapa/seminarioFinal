/* eslint-disable react-hooks/set-state-in-effect */
import { useCallback, useEffect, useState } from 'react';
import { ApiError } from '@/api/client';
import { recipesApi } from '@/api/endpoints';
import type { NormalizedError } from '@/types/api';
import type { RecipeCost, RecipeDetail, RecipeNutrition } from '@/types/recipe';

const FALLBACK: NormalizedError = { status: 0, code: 'UNKNOWN', message: 'Error desconocido.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false };

export function useRecipeDetail(recipeId: number) {
  const [data, setData] = useState<RecipeDetail | null>(null);
  const [nutrition, setNutrition] = useState<RecipeNutrition | null>(null);
  const [cost, setCost] = useState<RecipeCost | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);

  const refresh = useCallback(() => {
    if (!recipeId) return;
    setLoading(true);
    setError(null);
    Promise.all([
      recipesApi.get(recipeId),
      recipesApi.nutrition(recipeId).catch(() => ({ data: null })),
      recipesApi.cost(recipeId).catch(() => ({ data: null })),
    ])
      .then(([recipeRes, nutritionRes, costRes]) => {
        setData(recipeRes.data);
        setNutrition(nutritionRes.data);
        setCost(costRes.data);
      })
      .catch((err: unknown) => setError(err instanceof ApiError ? err.normalized : FALLBACK))
      .finally(() => setLoading(false));
  }, [recipeId]);

  useEffect(() => { refresh(); }, [refresh]);

  return { data, nutrition, cost, loading, error, refresh };
}
/* eslint-enable react-hooks/set-state-in-effect */

