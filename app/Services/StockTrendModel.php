<?php

namespace App\Services;

/** Ordinary least squares: weekly releases y(t) = a + b*t, t = 1,...,n. */
class StockTrendModel
{
    public function fit(array $weeklyTotals): array
    {
        $values = array_values($weeklyTotals);
        foreach ($values as $value) {
            if (! is_numeric($value) || ! is_finite((float) $value) || $value < 0) {
                throw new \InvalidArgumentException('Weekly releases must be finite nonnegative quantities.');
            }
        }
        $n = count($values);
        $mean = $n ? array_sum($values) / $n : 0.0;
        $slope = 0.0;
        $intercept = $mean;
        $rSquared = null;
        $method = $n ? 'weekly_average' : 'no_history';
        $trend = $n ? 'insufficient_history' : 'no_history';
        if ($n >= max(2, (int) config('forecasting.minimum_trend_weeks', 4))) {
            $xMean = ($n + 1) / 2;
            $sxx = $sxy = 0.0;
            foreach ($values as $index => $value) {
                $dx = $index + 1 - $xMean;
                $sxx += $dx * $dx;
                $sxy += $dx * ($value - $mean);
            }
            $slope = $sxy / $sxx;
            $intercept = $mean - $slope * $xMean;
            $sse = $sst = 0.0;
            foreach ($values as $index => $value) {
                $sse += ($value - ($intercept + $slope * ($index + 1))) ** 2;
                $sst += ($value - $mean) ** 2;
            }
            $rSquared = $sst > 0 ? max(0.0, min(1.0, 1 - $sse / $sst)) : 1.0;
            $method = 'linear_regression';
            $threshold = max(1e-9, $mean * (float) config('forecasting.stable_slope_fraction', 0.05));
            $trend = abs($slope) <= $threshold ? 'stable' : ($slope > 0 ? 'rising' : 'falling');
        }

        return [
            'method' => $method, 'periods' => $n, 'slope' => $slope, 'intercept' => $intercept,
            'r_squared' => $rSquared, 'historical_weekly_average' => $mean, 'trend' => $trend,
            'fitted' => array_map(fn ($t) => $intercept + $slope * $t, $n ? range(1, $n) : []),
        ];
    }

    public function weeklyPrediction(array $fit, int $weeksAhead): float
    {
        if ($weeksAhead < 1) {
            throw new \InvalidArgumentException('Forecast weeks must be positive.');
        }

        return max(0.0, $fit['intercept'] + $fit['slope'] * ($fit['periods'] + $weeksAhead));
    }

    public function dailyPredictions(array $fit, int $days): array
    {
        if ($days < 1) {
            throw new \InvalidArgumentException('Forecast days must be positive.');
        }
        $predictions = [];
        for ($day = 0; $day < $days; $day++) {
            $predictions[] = $this->weeklyPrediction($fit, intdiv($day, 7) + 1) / 7;
        }

        return $predictions;
    }
}
