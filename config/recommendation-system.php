<?php

/**
 * Configuration for the Recommendation System
 *
 * This configuration file is used to set up values for
 * Multi-Criteria Decision Making (MCDM) methods implemented in the system.
 *
 * Included Methods:
 * - ROC (Rank Order Centroid): Used for assigning weights to criteria.
 * - MULTIMOORA (Multi-Objective Optimization by Ratio Analysis plus Full Multiplicative Form):
 *   Used for evaluating and ranking alternatives based on the weighted criteria.
 */

return [

    /**
     * Data preprocessing configuration
     */
    'preprocessing' => [
        'alternatives_categorized_path' => 'lowongan_magang/alternatives_categorized.json',
    ],

    /**
     * ROC (Rank Order Centroid)
     */
    'roc' => [
        'total_criteria' => 5,
    ],
];
