<?php

namespace App\Console\Commands;

use App\Services\RecommendationEvaluator;
use App\Services\RecommendationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('recommendations:evaluate {--k=10 : Number of recommendations to evaluate per user}')]
#[Description('Measure Precision@K and Recall@K for the recommendation engine via leave-one-out evaluation')]
class EvaluateRecommendations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RecommendationService $recommendations, RecommendationEvaluator $evaluator): int
    {
        $k = (int) $this->option('k');
        $data = json_decode(Storage::get('movies.json'), true);

        $metrics = $evaluator->evaluate($data['users'], $k);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Precision@'.$metrics['k'], $metrics['precision_at_k']],
                ['Recall@'.$metrics['k'], $metrics['recall_at_k']],
                ['Users evaluated', $metrics['users_evaluated']],
            ]
        );

        return self::SUCCESS;
    }
}
