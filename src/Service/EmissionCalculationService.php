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
     */
    public function calculateEmissionsForAssessment(CarbonAssessment $assessment): void
    {
        $scope1 = $this->emissionRepository->calculateByScope($assessment->getId(), 1);
        $scope2 = $this->emissionRepository->calculateByScope($assessment->getId(), 2);
        $scope3 = $this->emissionRepository->calculateByScope($assessment->getId(), 3);
        $total = $scope1 + $scope2 + $scope3;

        $assessment->setScope1Emissions($scope1);
        $assessment->setScope2Emissions($scope2);
        $assessment->setScope3Emissions($scope3);
        $assessment->setTotalEmissions($total);

        $this->entityManager->persist($assessment);
        $this->entityManager->flush();
    }

    /**
     * Calculate emissions based on activity data and emission factor.
     * 
     * @param float $activityData The activity data (e.g., kWh of electricity, liters of fuel)
     * @param float $emissionFactor The emission factor (e.g., kgCO2e per kWh)
     * @return float The calculated emissions in tCO2e
     */
    public function calculateEmissions(float $activityData, float $emissionFactor): float
    {
        // Convert to tCO2e if the emission factor is in kgCO2e
        return $activityData * $emissionFactor / 1000;
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
        $amount = $this->calculateEmissions($activityData, $emissionFactor);
        
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
        
        // Update the assessment totals
        $this->calculateEmissionsForAssessment($assessment);
        
        return $emission;
    }

    /**
     * Get a breakdown of emissions by category for an assessment.
     * 
     * @param CarbonAssessment $assessment The assessment to analyze
     * @return array An array of categories with their total emissions
     */
    public function getEmissionsByCategory(CarbonAssessment $assessment): array
    {
        $emissions = $assessment->getEmissions();
        $categories = [];
        
        foreach ($emissions as $emission) {
            $category = $emission->getCategory();
            $amount = $emission->getAmount();
            
            if (!isset($categories[$category])) {
                $categories[$category] = 0;
            }
            
            $categories[$category] += $amount;
        }
        
        return $categories;
    }

    /**
     * Get a breakdown of emissions by scope for an assessment.
     * 
     * @param CarbonAssessment $assessment The assessment to analyze
     * @return array An array of scopes with their total emissions
     */
    public function getEmissionsByScope(CarbonAssessment $assessment): array
    {
        return [
            1 => $assessment->getScope1Emissions() ?? 0,
            2 => $assessment->getScope2Emissions() ?? 0,
            3 => $assessment->getScope3Emissions() ?? 0
        ];
    }
}