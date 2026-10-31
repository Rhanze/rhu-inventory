<?php

namespace App\Services;

/** Holt's additive level/trend; missing periods break the training run. */
class HoltDiseaseModel
{
    public function fit(array $series, int $horizon = 4): array
    {
        $tail = [];
        foreach ($series as $value) {
            if ($value === null) {
                $tail = [];

                continue;
            }
            if (! is_numeric($value) || ! is_finite((float) $value) || $value < 0) {
                throw new \InvalidArgumentException('Invalid disease count.');
            }
            $tail[] = (float) $value;
        }
        $alpha = (float) config('disease_analysis.holt_alpha', 0.4);
        $beta = (float) config('disease_analysis.holt_beta', 0.2);
        $result = ['status' => end($series) === null ? 'latest_period_missing' : 'insufficient_history',
            'observations' => count($tail), 'alpha' => $alpha, 'beta' => $beta,
            'level' => null, 'slope' => null, 'validation_mae' => null, 'naive_mae' => null,
            'validation_periods' => 0, 'predictions' => array_fill(0, $horizon, null)];
        if (count($tail) < (int) config('disease_analysis.minimum_observations', 4)) {
            return $result;
        }
        $holdout = max(1, (int) config('disease_analysis.holt_validation_periods', 3));
        if (count($tail) >= 8 && count($tail) - $holdout >= 4) {
            $training = array_slice($tail, 0, -$holdout);
            $best = $this->smooth($training, $alpha, $beta)['training_mae'];
            foreach ([0.2, 0.4, 0.6, 0.8] as $candidateAlpha) {
                foreach ([0.1, 0.2, 0.4] as $candidateBeta) {
                    $score = $this->smooth($training, $candidateAlpha, $candidateBeta)['training_mae'];
                    if ($score < $best - 1e-9) {
                        $best = $score;
                        $alpha = $candidateAlpha;
                        $beta = $candidateBeta;
                    }
                }
            }
            $state = $this->smooth($training, $alpha, $beta);
            $errors = $naive = [];
            $previous = end($training);
            foreach (array_slice($tail, -$holdout) as $value) {
                $errors[] = abs($value - max(0, $state['level'] + $state['slope']));
                $naive[] = abs($value - $previous);
                $state = $this->update($state, $value, $alpha, $beta);
                $previous = $value;
            }
            $result['validation_mae'] = array_sum($errors) / $holdout;
            $result['naive_mae'] = array_sum($naive) / $holdout;
            $result['validation_periods'] = $holdout;
        }
        $state = $this->smooth($tail, $alpha, $beta);

        return array_replace($result, ['status' => 'ready', 'alpha' => $alpha, 'beta' => $beta,
            'level' => $state['level'], 'slope' => $state['slope'],
            'predictions' => array_map(fn ($h) => max(0, $state['level'] + $h * $state['slope']), range(1, $horizon))]);
    }

    public function update(array $state, float $value, float $alpha, float $beta): array
    {
        if ($alpha < 0 || $alpha > 1 || $beta < 0 || $beta > 1) {
            throw new \InvalidArgumentException('Smoothing weights must be between zero and one.');
        }
        $level = $alpha * $value + (1 - $alpha) * ($state['level'] + $state['slope']);
        $slope = $beta * ($level - $state['level']) + (1 - $beta) * $state['slope'];

        return ['level' => $level, 'slope' => $slope];
    }

    private function smooth(array $values, float $alpha, float $beta): array
    {
        $state = ['level' => $values[1], 'slope' => $values[1] - $values[0]];
        $errors = [];
        foreach (array_slice($values, 2) as $value) {
            $errors[] = abs($value - max(0, $state['level'] + $state['slope']));
            $state = $this->update($state, $value, $alpha, $beta);
        }

        return $state + ['training_mae' => count($errors) ? array_sum($errors) / count($errors) : 0];
    }
}
