<?php

namespace App\Services;

class DiseaseRegressionModel
{
    /** Missing observations retain their time coordinates and never become zero cases. */
    public function fit(array $series, int $horizon = 4): array
    {
        $series = array_values($series);
        $points = [];
        foreach (array_values($series) as $index => $value) {
            if ($value === null) {
                continue;
            }
            if (! is_numeric($value) || ! is_finite((float) $value) || $value < 0) {
                throw new \InvalidArgumentException('Invalid disease count.');
            }
            $points[] = ['x' => $index + 1, 'y' => (float) $value];
        }
        $n = count($points);
        $output = ['observations' => $n, 'slope' => null, 'intercept' => null, 'r_squared' => null,
            'direction' => 'insufficient_history', 'status' => 'insufficient_history', 'fitted' => array_fill(0, count($series), null), 'predictions' => array_fill(0, $horizon, null)];
        if ($n < (int) config('disease_analysis.minimum_observations', 4)) {
            return $output;
        }
        $xMean = array_sum(array_column($points, 'x')) / $n;
        $yMean = array_sum(array_column($points, 'y')) / $n;
        $sxx = $sxy = 0.0;
        foreach ($points as $point) {
            $sxx += ($point['x'] - $xMean) ** 2;
            $sxy += ($point['x'] - $xMean) * ($point['y'] - $yMean);
        }
        $slope = $sxy / $sxx;
        $intercept = $yMean - $slope * $xMean;
        $sse = $sst = 0.0;
        foreach ($points as $point) {
            $sse += ($point['y'] - ($intercept + $slope * $point['x'])) ** 2;
            $sst += ($point['y'] - $yMean) ** 2;
        }
        $threshold = max(1e-9, $yMean * (float) config('disease_analysis.stable_slope_fraction', 0.05));
        $direction = abs($slope) <= $threshold ? 'stable' : ($slope > 0 ? 'rising' : 'declining');
        $recentMissing = end($series) === null;

        return ['observations' => $n, 'slope' => $slope, 'intercept' => $intercept,
            'r_squared' => $sst > 0 ? max(0, min(1, 1 - $sse / $sst)) : 1.0,
            'direction' => $recentMissing ? 'latest_period_missing' : $direction,
            'status' => $recentMissing ? 'latest_period_missing' : 'ready',
            'fitted' => array_map(fn ($i) => $series[$i - 1] === null ? null : $intercept + $slope * $i, range(1, count($series))),
            'predictions' => $recentMissing ? $output['predictions'] : array_map(fn ($h) => max(0, $intercept + $slope * (count($series) + $h)), range(1, $horizon))];
    }
}
