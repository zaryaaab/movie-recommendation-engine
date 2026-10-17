<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EvaluateRecommendationsCommandTest extends TestCase
{
    public function test_command_runs_successfully_and_reports_metrics_within_valid_bounds(): void
    {
        $exitCode = Artisan::call('recommendations:evaluate', ['--k' => 10]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);

        $this->assertMatchesRegularExpression('/Precision@10\s*\|\s*([\d.]+)/', $output);
        $this->assertMatchesRegularExpression('/Recall@10\s*\|\s*([\d.]+)/', $output);
        $this->assertMatchesRegularExpression('/Users evaluated\s*\|\s*(\d+)/', $output);

        preg_match('/Precision@10\s*\|\s*([\d.]+)/', $output, $precisionMatch);
        preg_match('/Recall@10\s*\|\s*([\d.]+)/', $output, $recallMatch);
        preg_match('/Users evaluated\s*\|\s*(\d+)/', $output, $usersMatch);

        $this->assertGreaterThan(0, (int) $usersMatch[1]);
        $this->assertGreaterThanOrEqual(0.0, (float) $precisionMatch[1]);
        $this->assertLessThanOrEqual(1.0, (float) $precisionMatch[1]);
        $this->assertGreaterThanOrEqual(0.0, (float) $recallMatch[1]);
        $this->assertLessThanOrEqual(1.0, (float) $recallMatch[1]);
    }
}
