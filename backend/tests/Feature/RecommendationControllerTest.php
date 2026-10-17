<?php

namespace Tests\Feature;

use Tests\TestCase;

class RecommendationControllerTest extends TestCase
{
    public function test_movies_endpoint_returns_full_catalog_sorted_by_title(): void
    {
        $response = $this->getJson('/api/movies');

        $response->assertOk();
        $titles = $response->json('*.title');
        $sorted = $titles;
        sort($sorted);

        $this->assertSame($sorted, $titles);
        $this->assertGreaterThan(0, count($titles));
    }

    public function test_recommend_endpoint_rejects_empty_movie_ids(): void
    {
        $response = $this->postJson('/api/recommend', ['movie_ids' => []]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('movie_ids');
    }

    public function test_recommend_endpoint_rejects_missing_movie_ids(): void
    {
        $response = $this->postJson('/api/recommend', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('movie_ids');
    }

    public function test_recommend_endpoint_returns_scored_and_explained_recommendations(): void
    {
        $response = $this->postJson('/api/recommend', [
            'movie_ids' => [1, 9, 24],
            'limit' => 5,
        ]);

        $response->assertOk();
        $response->assertJsonCount(5, 'recommendations');
        $response->assertJsonStructure([
            'recommendations' => [
                '*' => ['id', 'title', 'score', 'reason'],
            ],
        ]);

        $recommendedIds = $response->json('recommendations.*.id');
        $this->assertEmpty(array_intersect([1, 9, 24], $recommendedIds));
        $this->assertSame(count($recommendedIds), count(array_unique($recommendedIds)));
    }

    public function test_recommend_endpoint_respects_custom_limit(): void
    {
        $response = $this->postJson('/api/recommend', [
            'movie_ids' => [1],
            'limit' => 3,
        ]);

        $response->assertOk();
        $response->assertJsonCount(3, 'recommendations');
    }

    public function test_recommend_endpoint_ignores_unknown_movie_ids_without_erroring(): void
    {
        $response = $this->postJson('/api/recommend', [
            'movie_ids' => [999999],
            'limit' => 5,
        ]);

        $response->assertOk();
        $response->assertJsonCount(5, 'recommendations');
    }
}
