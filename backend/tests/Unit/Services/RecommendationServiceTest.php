<?php

namespace Tests\Unit\Services;

use App\Services\RecommendationService;
use Tests\TestCase;

class RecommendationServiceTest extends TestCase
{
    /**
     * Two tight clusters: movies 1 & 2 are co-liked by five users (a strong
     * signal), movie 3 is co-liked with movie 1 by only one user (a weak,
     * shrinkage-prone signal), and movies 4 & 5 form an unrelated cluster that
     * shares no users with 1/2/3.
     */
    private function service(): RecommendationService
    {
        return new RecommendationService([
            'movies' => [
                1 => 'Movie A',
                2 => 'Movie B',
                3 => 'Movie C',
                4 => 'Movie D',
                5 => 'Movie E',
            ],
            'users' => [
                ['user_id' => 1, 'movies' => [1, 2]],
                ['user_id' => 2, 'movies' => [1, 2]],
                ['user_id' => 3, 'movies' => [1, 2]],
                ['user_id' => 4, 'movies' => [1, 2]],
                ['user_id' => 5, 'movies' => [1, 2]],
                ['user_id' => 6, 'movies' => [1, 3]],
                ['user_id' => 7, 'movies' => [4, 5]],
                ['user_id' => 8, 'movies' => [4, 5]],
                ['user_id' => 9, 'movies' => [4, 5]],
            ],
        ]);
    }

    public function test_all_movies_returns_full_catalog(): void
    {
        $movies = $this->service()->allMovies();

        $this->assertSame([
            1 => 'Movie A',
            2 => 'Movie B',
            3 => 'Movie C',
            4 => 'Movie D',
            5 => 'Movie E',
        ], $movies);
    }

    public function test_recommend_excludes_already_liked_movies(): void
    {
        $results = $this->service()->recommend([1, 2], 10);

        $recommendedIds = array_column($results, 'id');

        $this->assertNotContains(1, $recommendedIds);
        $this->assertNotContains(2, $recommendedIds);
    }

    public function test_recommend_ranks_strongly_co_liked_movie_above_weakly_co_liked_movie(): void
    {
        $results = $this->service()->recommend([1], 10);
        $byId = collect($results)->keyBy('id');

        $this->assertGreaterThan($byId[3]['score'], $byId[2]['score']);
    }

    public function test_recommend_deduplicates_repeated_liked_movie_ids(): void
    {
        $withDuplicates = $this->service()->recommend([1, 1, 2], 10);
        $withoutDuplicates = $this->service()->recommend([1, 2], 10);

        $this->assertSame(
            array_column($withoutDuplicates, 'id'),
            array_column($withDuplicates, 'id')
        );
    }

    public function test_recommend_never_returns_duplicate_movie_ids(): void
    {
        $results = $this->service()->recommend([1], 10);

        $ids = array_column($results, 'id');

        $this->assertSame(count($ids), count(array_unique($ids)));
    }

    public function test_recommend_explains_score_using_the_contributing_liked_movie(): void
    {
        $results = $this->service()->recommend([1], 10);
        $byId = collect($results)->keyBy('id');

        $this->assertStringContainsString('Movie A', $byId[2]['reason']);
    }

    public function test_recommend_falls_back_to_popularity_when_collaborative_signal_runs_out(): void
    {
        // Only movie 2 and 3 carry collaborative signal from liking movie 1;
        // movies 4 and 5 share no users with movie 1 at all.
        $results = $this->service()->recommend([1], 10);

        $byId = collect($results)->keyBy('id');

        $this->assertCount(4, $results);
        $this->assertStringContainsString('Popular', $byId[4]['reason']);
        $this->assertStringContainsString('Popular', $byId[5]['reason']);
    }

    public function test_recommend_falls_back_to_pure_popularity_when_liked_movies_are_unknown(): void
    {
        $results = $this->service()->recommend([999], 3);

        $this->assertCount(3, $results);
        $this->assertSame([1, 2, 4], array_column($results, 'id'));
        foreach ($results as $result) {
            $this->assertStringContainsString('Popular', $result['reason']);
        }
    }

    public function test_recommend_respects_the_requested_limit(): void
    {
        $results = $this->service()->recommend([1], 2);

        $this->assertCount(2, $results);
    }
}
