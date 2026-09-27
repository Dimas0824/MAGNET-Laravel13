<?php

namespace App\Traits;

use App\Helpers\DecisionMaking\ROC;

trait BaseKriteriaFactory
{
    public function forUser(int $mhs_id)
    {
        return $this->state(fn () => [
            'mahasiswa_id' => $mhs_id,
        ]);
    }

    public function rank(int $rank)
    {
        return $this->state(fn () => [
            'rank' => $rank,
        ]);
    }

    public function withBobotCalculation()
    {
        return $this->afterMaking(function ($model) {
            $model->bobot = ROC::getWeight(
                rank: $model->rank,
                totalCriteria: config('recommendation-system.roc.total_criteria')
            );
        });
    }
}
