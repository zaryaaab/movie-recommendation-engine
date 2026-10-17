<?php

namespace App\Http\Controllers;

use App\Services\RecommendationService;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function __construct(private RecommendationService $recommendations)
    {
    }

    public function movies()
    {
        $movies = collect($this->recommendations->allMovies())
            ->map(fn ($title, $id) => ['id' => (int) $id, 'title' => $title])
            ->sortBy('title')
            ->values();

        return response()->json($movies);
    }

    public function recommend(Request $request)
    {
        $validated = $request->validate([
            'movie_ids' => ['required', 'array', 'min:1'],
            'movie_ids.*' => ['integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:69'],
        ]);

        $results = $this->recommendations->recommend(
            $validated['movie_ids'],
            $validated['limit'] ?? 10
        );

        return response()->json(['recommendations' => $results]);
    }
}
