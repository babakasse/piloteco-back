<?php

namespace App\Service;

use App\Entity\Company;
use App\Entity\CarbonAssessment;
use App\Repository\CarbonAssessmentRepository;

class AssessmentService
{
    public function __construct(private readonly CarbonAssessmentRepository $assessmentRepository)
    {
    }

    /**
     * Retourne le dernier bilan carbone d'une entreprise
     */
    public function getLatestAssessmentForCompany(Company $company): ?CarbonAssessment
    {
        return $this->assessmentRepository->findOneBy(
            ['company' => $company],
            ['assessmentDate' => 'DESC', 'id' => 'DESC']
        );
    }

    /**
     * Retourne tous les bilans carbone d'une entreprise, éventuellement filtrés par année
     */
    public function getAssessmentsForCompany(Company $company, ?int $year = null): array
    {
        if ($year) {
            return $this->assessmentRepository->findByCompanyAndYear($company->getId(), $year);
        }
        return $this->assessmentRepository->findByCompany($company->getId());
    }

    /**
     * Retourne un bilan carbone par son id et la société (sécurisé)
     */
    public function getAssessmentForCompanyById(Company $company, int $id): ?CarbonAssessment
    {
        $assessment = $this->assessmentRepository->find($id);
        if ($assessment && $assessment->getCompany()->getId() === $company->getId()) {
            return $assessment;
        }
        return null;
    }

    /**
     * Crée un nouveau bilan carbone pour une entreprise
     */
    public function createAssessment(Company $company, array $data): CarbonAssessment
    {
        $assessment = new CarbonAssessment();
        $assessment->setName($data['name'] ?? 'Carbon Assessment');
        $assessment->setDescription($data['description'] ?? null);
        $assessment->setCompany($company);
        if (isset($data['assessmentDate'])) {
            $assessment->setAssessmentDate(new \DateTime($data['assessmentDate']));
        }
        $assessment->setStatus($data['status'] ?? 'draft');
        $assessment->setYear((int)$data['year'] ?? null);
        return $assessment;
    }

    /**
     * Met à jour un bilan carbone existant (hors émissions)
     */
    public function updateAssessment(CarbonAssessment $assessment, array $data): CarbonAssessment
    {
        if (isset($data['name'])) {
            $assessment->setName($data['name']);
        }
        if (isset($data['description'])) {
            $assessment->setDescription($data['description']);
        }
        if (isset($data['assessmentDate'])) {
            $assessment->setAssessmentDate(new \DateTime($data['assessmentDate']));
        }
        if (isset($data['status'])) {
            $assessment->setStatus($data['status']);
        }
        if(isset($data['year'])) {
            $assessment->setYear((int)$data['year']);
        }
        return $assessment;
    }
}
