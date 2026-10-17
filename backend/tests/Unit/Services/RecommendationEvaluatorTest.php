<?php

namespace Tests\Unit\Services;

use App\Services\RecommendationEvaluator;
use App\Services\RecommendationService;
use Tests\TestCase;

class RecommendationEvaluatorTest extends TestCase
{
    public function test_evaluate_scores_perfect_recovery_as_full_precision_and_recall(): void
    {
        // Two disjoint clusters, each liked identically by every user in it:
        // holding out each user's last movie should always be recoverable
        // from the other two, since nothing else in the catalog is similar.
        $data = [
            'movies' => [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'F'],
            'users' => array_merge(
                array_map(fn ($i) => ['user_id' => $i, 'movies' => [1, 2, 3]], range(1, 10)),
                array_map(fn ($i) => ['user_id' => $i, 'movies' => [4, 5, 6]], range(11, 20)),
            ),
        ];

        $service = new RecommendationService($data);
        $evaluator = new RecommendationEvaluator($service);

        $metrics = $evaluator->evaluate($data['users'], k: 1);

        $this->assertSame(1.0, $metrics['precision_at_k']);
        $this->assertSame(1.0, $metrics['recall_at_k']);
        $this->assertSame(1, $metrics['k']);
        $this->assertSame(20, $metrics['users_evaluated']);
    }

    public function test_evaluate_skips_users_without_enough_liked_movies(): void
    {
        $data = [
            'movies' => [1 => 'A', 2 => 'B', 3 => 'C'],
            'users' => [
                ['user_id' => 1, 'movies' => [1]],
                ['user_id' => 2, 'movies' => [2]],
            ],
        ];

        $service = new RecommendationService($data);
        $evaluator = new RecommendationEvaluator($service);

        $metrics = $evaluator->evaluate($data['users'], k: 1, minLikedForEval: 2);

        $this->assertSame(0, $metrics['users_evaluated']);
        $this->assertSame(0.0, $metrics['precision_at_k']);
        $this->assertSame(0.0, $metrics['recall_at_k']);
    }
}
