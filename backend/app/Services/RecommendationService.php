<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class RecommendationService
{
    /**
     * Significance-weighting constant for similarity shrinkage. Pairs of movies
     * co-liked by very few users get their similarity pulled toward zero so a
     * single coincidental overlap can't look like a strong signal.
     */
    private const SHRINKAGE = 5.0;

    /** @var array<int, string> movie id => title */
    private array $movies = [];

    /** @var array<int, array<int, true>> movie id => set of user ids who like it */
    private array $likersByMovie = [];

    /** @var array<int, array<int, float>> movie id => [other movie id => similarity] */
    private array $similarity = [];

    /** @var int[] movie ids ordered by popularity, most liked first */
    private array $popularityRank = [];

    private int $totalUsers = 0;

    /**
     * @param  array{movies: array<int|string, string>, users: array<int, array{user_id:int, movies:int[]}>}|null  $data
     *                                                                                                                    Pass an explicit dataset for testing; defaults to the stored catalog.
     */
    public function __construct(?array $data = null)
    {
        $data ??= json_decode(Storage::get('movies.json'), true);

        foreach ($data['movies'] as $id => $title) {
            $this->movies[(int) $id] = $title;
        }

        foreach ($this->movies as $id => $title) {
            $this->likersByMovie[$id] = [];
        }

        $this->totalUsers = count($data['users']);

        foreach ($data['users'] as $user) {
            foreach ($user['movies'] as $movieId) {
                if (isset($this->likersByMovie[$movieId])) {
                    $this->likersByMovie[$movieId][$user['user_id']] = true;
                }
            }
        }

        $this->buildSimilarityMatrix();
        $this->buildPopularityRank();
    }

    /**
     * @return array<int, string>
     */
    public function allMovies(): array
    {
        return $this->movies;
    }

    /**
     * Item-based collaborative filtering: for each candidate movie not already
     * liked, score it by the average shrinkage-adjusted cosine similarity
     * between it and every movie the user likes. Averaging (rather than
     * summing) over the full liked set means the score reflects how broadly a
     * candidate fits the user's taste, not just how many movies were selected.
     *
     * When too few candidates carry collaborative signal (e.g. a very small or
     * unusual selection), remaining slots are filled with popularity-based
     * picks so the response still returns up to `limit` results.
     *
     * @param  int[]  $likedMovieIds
     * @return array<int, array{id: int, title: string, score: float, reason: string}>
     */
    public function recommend(array $likedMovieIds, int $limit = 10): array
    {
        $liked = array_values(array_unique(array_map('intval', array_filter(
            $likedMovieIds,
            fn ($id) => isset($this->movies[(int) $id])
        ))));
        $likedSet = array_flip($liked);

        $results = $this->scoreByCollaborativeFiltering($liked, $likedSet);

        uasort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

        $results = array_slice($results, 0, $limit, true);

        if (count($results) < $limit) {
            $results = $this->fillWithPopularFallback($results, $likedSet, $limit);
        }

        return array_values($results);
    }

    /**
     * @param  int[]  $liked
     * @param  array<int, int>  $likedSet
     * @return array<int, array{id: int, title: string, score: float, reason: string}>
     */
    private function scoreByCollaborativeFiltering(array $liked, array $likedSet): array
    {
        if (empty($liked)) {
            return [];
        }

        $scores = [];

        foreach ($this->movies as $candidateId => $title) {
            if (isset($likedSet[$candidateId])) {
                continue;
            }

            $contributions = [];
            foreach ($liked as $likedId) {
                $sim = $this->similarity[$candidateId][$likedId] ?? 0.0;
                if ($sim > 0.0) {
                    $contributions[$likedId] = $sim;
                }
            }

            if (empty($contributions)) {
                continue;
            }

            arsort($contributions);

            $scores[$candidateId] = [
                'id' => $candidateId,
                'title' => $title,
                'score' => round(array_sum($contributions) / count($liked), 4),
                'reason' => $this->explainFromLikedMovies($contributions),
            ];
        }

        return $scores;
    }

    /**
     * @param  array<int, array{id: int, title: string, score: float, reason: string}>  $results
     * @param  array<int, int>  $likedSet
     * @return array<int, array{id: int, title: string, score: float, reason: string}>
     */
    private function fillWithPopularFallback(array $results, array $likedSet, int $limit): array
    {
        $excluded = $likedSet + $results;

        foreach ($this->popularityRank as $movieId) {
            if (count($results) >= $limit) {
                break;
            }

            if (isset($excluded[$movieId])) {
                continue;
            }

            $results[$movieId] = [
                'id' => $movieId,
                'title' => $this->movies[$movieId],
                'score' => round($this->popularity($movieId), 4),
                'reason' => 'Popular with other viewers: not enough shared-taste data for a more targeted match',
            ];
            $excluded[$movieId] = true;
        }

        return $results;
    }

    /**
     * @param  array<int, float>  $contributionsByLikedMovieId  similarity to each liked movie that drove the score, highest first
     */
    private function explainFromLikedMovies(array $contributions): string
    {
        $titles = array_map(
            fn ($likedId) => $this->movies[$likedId],
            array_slice(array_keys($contributions), 0, 2)
        );

        return 'Because you liked '.implode(' and ', $titles);
    }

    private function buildSimilarityMatrix(): void
    {
        $ids = array_keys($this->movies);
        $count = count($ids);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $ids[$i];
                $b = $ids[$j];
                $sim = $this->computeSimilarity($a, $b);

                if ($sim > 0.0) {
                    $this->similarity[$a][$b] = $sim;
                    $this->similarity[$b][$a] = $sim;
                }
            }
        }
    }

    /**
     * Cosine similarity between the two movies' liker sets, shrunk toward zero
     * when the number of users who co-like both is small. This keeps a single
     * coincidental overlap (common with ~70 movies and sparse per-user taste)
     * from producing a misleadingly high similarity score.
     */
    private function computeSimilarity(int $movieA, int $movieB): float
    {
        $likersA = $this->likersByMovie[$movieA] ?? [];
        $likersB = $this->likersByMovie[$movieB] ?? [];

        if (empty($likersA) || empty($likersB)) {
            return 0.0;
        }

        $cooccurrence = count(array_intersect_key($likersA, $likersB));

        if ($cooccurrence === 0) {
            return 0.0;
        }

        $cosine = $cooccurrence / sqrt(count($likersA) * count($likersB));
        $confidence = $cooccurrence / ($cooccurrence + self::SHRINKAGE);

        return $cosine * $confidence;
    }

    private function buildPopularityRank(): void
    {
        $counts = array_map(static fn ($likers) => count($likers), $this->likersByMovie);
        arsort($counts);
        $this->popularityRank = array_keys($counts);
    }

    private function popularity(int $movieId): float
    {
        return count($this->likersByMovie[$movieId] ?? []) / max(1, $this->totalUsers);
    }
}
