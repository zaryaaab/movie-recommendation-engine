<?php

namespace App\Services;

class RecommendationEvaluator
{
    public function __construct(private RecommendationService $recommendations) {}

    /**
     * Leave-one-out offline evaluation. For each eligible user, a slice of
     * their liked movies is hidden, the rest are fed back into recommend(),
     * and Precision@K / Recall@K measure how many hidden movies were
     * recovered in the top-K results.
     *
     * The holdout slice is chosen deterministically (highest movie ids in the
     * user's liked set) rather than randomly, so repeated runs against the
     * same dataset produce the same metrics.
     *
     * @param  array<int, array{user_id:int, movies:int[]}>  $users
     * @return array{precision_at_k: float, recall_at_k: float, k: int, users_evaluated: int}
     */
    public function evaluate(array $users, int $k = 10, int $minLikedForEval = 2, float $holdoutRatio = 0.2): array
    {
        $precisionSum = 0.0;
        $recallSum = 0.0;
        $evaluated = 0;

        foreach ($users as $user) {
            $liked = array_values(array_unique($user['movies']));
            sort($liked);

            if (count($liked) < $minLikedForEval) {
                continue;
            }

            $holdoutCount = min(
                count($liked) - 1,
                max(1, (int) round(count($liked) * $holdoutRatio))
            );

            $holdout = array_slice($liked, -$holdoutCount);
            $input = array_slice($liked, 0, count($liked) - $holdoutCount);

            $recommended = $this->recommendations->recommend($input, $k);
            $recommendedIds = array_column($recommended, 'id');

            $hits = count(array_intersect($recommendedIds, $holdout));

            $precisionSum += count($recommendedIds) > 0 ? $hits / count($recommendedIds) : 0.0;
            $recallSum += $hits / count($holdout);
            $evaluated++;
        }

        return [
            'precision_at_k' => $evaluated > 0 ? round($precisionSum / $evaluated, 4) : 0.0,
            'recall_at_k' => $evaluated > 0 ? round($recallSum / $evaluated, 4) : 0.0,
            'k' => $k,
            'users_evaluated' => $evaluated,
        ];
    }
}
