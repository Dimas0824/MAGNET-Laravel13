<?php

namespace App\Helpers\DecisionMaking;

class ROC
{
    public static function getWeight(int $rank, int $totalCriteria): float
    {
        $partialHarmonicSum = 0;
        for ($i = $rank; $i <= $totalCriteria; $i++) {
            $partialHarmonicSum += 1 / $i;
        }

        return 1 / $totalCriteria * $partialHarmonicSum;
    }
}
