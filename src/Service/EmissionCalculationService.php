<?php

namespace App\Service;

use App\Entity\CarbonAssessment;
use App\Entity\Emission;
use App\Repository\EmissionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for calculating carbon emissions.
 */
class EmissionCalculationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmissionRepository $emissionRepository
    ) {
    }

    /**
     * Calculate and update emissions for an assessment.
     * @deprecated Use updateCalculations() instead, which is automatically called by entity lifecycle events
     */
    public function calculateEmissionsForAssessment(CarbonAssessment $assessment): void
    {
        $assessment->updateCalculations();
        $this->entityManager->persist($assessment);
        $this->entityManager->flush();
    }

    /**
     * Calculate emissions based on activity data and emission factor.
     * 
     * @param float $activityData The activity data (e.g., kWh of electricity, liters of fuel)
     * @param float $emissionFactor The emission factor (e.g., kgCO2e per kWh)
     * @param string $unit The unit for the result ('tCO2e' or 'kgCO2e')
     * @return float The calculated emissions in the specified unit, rounded to 2 decimal places
     */
    public function calculateEmissions(float $activityData, float $emissionFactor, string $unit = 'tCO2e'): float
    {
        $result = $activityData * $emissionFactor;

        // Convert to tCO2e if the result should be in tCO2e but factor is in kgCO2e
        if ($unit === 'tCO2e') {
            $result = $result / 1000;
        }

        return round($result, 2);
    }

    /**
     * Add a new emission to an assessment with automatic calculation.
     * 
     * @param CarbonAssessment $assessment The assessment to add the emission to
     * @param string $source The source of the emission
     * @param string $category The category of the emission
     * @param float $activityData The activity data
     * @param float $emissionFactor The emission factor
     * @param int $scope The scope (1, 2, or 3)
     * @param string $unit The unit of measurement for the result
     * @param string|null $description Optional description
     * @return Emission The created emission
     */
    public function addEmissionWithCalculation(
        CarbonAssessment $assessment,
        string $source,
        string $category,
        float $activityData,
        float $emissionFactor,
        int $scope,
        string $unit = 'tCO2e',
        ?string $description = null
    ): Emission {
        $amount = $this->calculateEmissions($activityData, $emissionFactor, $unit);

        $emission = new Emission();
        $emission->setAssessment($assessment);
        $emission->setSource($source);
        $emission->setCategory($category);
        $emission->setAmount($amount);
        $emission->setScope($scope);
        $emission->setUnit($unit);
        
        if ($description) {
            $emission->setDescription($description);
        }
        
        $this->entityManager->persist($emission);
        $this->entityManager->flush();
        
        // Les calculs sont automatiquement mis à jour via les événements PrePersist/PreUpdate

        return $emission;
    }

    /**
     * Get a breakdown of emissions by category for an assessment.
     * 
     * @param CarbonAssessment $assessment The assessment to analyze
     * @return array An array of categories with their total emissions (in tCO2e, rounded to 2 decimals)
     */
    public function getEmissionsByCategory(CarbonAssessment $assessment): array
    {
        $emissions = $assessment->getEmissions();
        $categories = [];
        
        foreach ($emissions as $emission) {
            $category = $emission->getCategory();
            $amount = $emission->getAmount() ?? 0;
            $unit = $emission->getUnit();

            // Convert to tCO2e if necessary
            if ($unit === 'kgCO2e') {
                $amount = $amount / 1000;
            }

            if (!isset($categories[$category])) {
                $categories[$category] = 0;
            }
            
            $categories[$category] += $amount;
        }
        
        // Round all values to 2 decimal places
        foreach ($categories as $category => $amount) {
            $categories[$category] = round($amount, 2);
        }

        return $categories;
    }

    /**
     * Get a breakdown of emissions by scope for an assessment.
     * 
     * @param CarbonAssessment $assessment The assessment to analyze
     * @return array An array of scopes with their total emissions (in tCO2e, rounded to 2 decimals)
     */
    public function getEmissionsByScope(CarbonAssessment $assessment): array
    {
        return [
            1 => round($assessment->getScope1Emissions() ?? 0, 2),
            2 => round($assessment->getScope2Emissions() ?? 0, 2),
            3 => round($assessment->getScope3Emissions() ?? 0, 2)
        ];
    }
}