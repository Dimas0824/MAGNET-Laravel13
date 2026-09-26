<?php

use App\Helpers\DecisionMaking\ROC;

// ROC (Rank Order Centroid) weights the criteria by rank: weight(i) = 1/n * sum_{k=i..n} 1/k

it('computes known ROC weights for three criteria', function () {
    expect(round(ROC::getWeight(1, 3), 4))->toBe(0.6111)
        ->and(round(ROC::getWeight(2, 3), 4))->toBe(0.2778)
        ->and(round(ROC::getWeight(3, 3), 4))->toBe(0.1111);
});

it('computes ROC weights that sum to 1 for the app criteria count', function () {
    $total = config('recommendation-system.roc.total_criteria');

    $sum = 0.0;
    for ($rank = 1; $rank <= $total; $rank++) {
        $sum += ROC::getWeight($rank, $total);
    }

    expect(round($sum, 10))->toBe(1.0);
});

it('gives strictly decreasing weights as rank increases', function () {
    $total = config('recommendation-system.roc.total_criteria');

    $weights = [];
    for ($rank = 1; $rank <= $total; $rank++) {
        $weights[$rank] = ROC::getWeight($rank, $total);
    }

    for ($rank = 1; $rank < $total; $rank++) {
        expect($weights[$rank])->toBeGreaterThan($weights[$rank + 1]);
    }
});

it('returns 1 for a single criterion', function () {
    expect(ROC::getWeight(1, 1))->toBe(1.0);
});
