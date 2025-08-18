<?php

namespace App\Controller;

use App\Entity\CarbonAssessment;
use App\Repository\CarbonAssessmentRepository;
use App\Repository\CompanyRepository;
use App\Service\EmissionCalculationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;


class AssessmentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly CarbonAssessmentRepository $assessmentRepository,
        private readonly CompanyRepository $companyRepository,
        private readonly EmissionCalculationService $calculationService
    ) {
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getAssessments(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $company = $user->getCompany();

        if (!$company) {
            return $this->json(['error' => 'User does not belong to a company'], Response::HTTP_BAD_REQUEST);
        }

        $year = $request->query->get('year');

        if ($year) {
            $assessments = $this->assessmentRepository->findByCompanyAndYear($company->getId(), (int) $year);
        } else {
            $assessments = $this->assessmentRepository->findByCompany($company->getId());
        }

        return $this->json(
            $assessments,
            Response::HTTP_OK,
            [],
            ['groups' => 'carbon_assessment:read']
        );
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getAssessment(int $id): JsonResponse
    {
        $user = $this->getUser();
        $company = $user->getCompany();

        if (!$company) {
            return $this->json(['error' => 'User does not belong to a company'], Response::HTTP_BAD_REQUEST);
        }

        $assessment = $this->assessmentRepository->find($id);

        if (!$assessment) {
            return $this->json(['error' => 'Assessment not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if the assessment belongs to the user's company
        if ($assessment->getCompany()->getId() !== $company->getId()) {
            return $this->json(['error' => 'Access denied to this assessment'], Response::HTTP_FORBIDDEN);
        }

        return $this->json(
            $assessment,
            Response::HTTP_OK,
            [],
            ['groups' => 'carbon_assessment:read']
        );
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function createAssessment(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $company = $user->getCompany();

        if (!$company) {
            return $this->json(['error' => 'User does not belong to a company'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);

        $assessment = new CarbonAssessment();
        $assessment->setName($data['name'] ?? 'Carbon Assessment');
        $assessment->setDescription($data['description'] ?? null);
        $assessment->setCompany($company);

        if (isset($data['assessmentDate'])) {
            $assessment->setAssessmentDate(new \DateTime($data['assessmentDate']));
        }

        $assessment->setStatus($data['status'] ?? 'draft');

        // Validate the assessment
        $violations = $this->validator->validate($assessment);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }
            return $this->json(['error' => 'Validation failed', 'details' => $errors], Response::HTTP_BAD_REQUEST);
        }

        // Save the assessment
        $this->entityManager->persist($assessment);
        $this->entityManager->flush();

        // Add emissions if provided
        if (isset($data['emissions']) && is_array($data['emissions'])) {
            foreach ($data['emissions'] as $emissionData) {
                $this->calculationService->addEmissionWithCalculation(
                    $assessment,
                    $emissionData['source'],
                    $emissionData['category'],
                    $emissionData['activityData'],
                    $emissionData['emissionFactor'],
                    $emissionData['scope'],
                    $emissionData['unit'] ?? 'tCO2e',
                    $emissionData['description'] ?? null
                );
            }
        }

        return $this->json(
            $assessment,
            Response::HTTP_CREATED,
            [],
            ['groups' => 'carbon_assessment:read']
        );
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getAssessmentEmissions(int $id): JsonResponse
    {
        $user = $this->getUser();
        $company = $user->getCompany();

        if (!$company) {
            return $this->json(['error' => 'User does not belong to a company'], Response::HTTP_BAD_REQUEST);
        }

        $assessment = $this->assessmentRepository->find($id);

        if (!$assessment) {
            return $this->json(['error' => 'Assessment not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if the assessment belongs to the user's company
        if ($assessment->getCompany()->getId() !== $company->getId()) {
            return $this->json(['error' => 'Access denied to this assessment'], Response::HTTP_FORBIDDEN);
        }

        return $this->json(
            $assessment->getEmissions(),
            Response::HTTP_OK,
            [],
            ['groups' => 'emission:read']
        );
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getAssessmentSummary(int $id): JsonResponse
    {
        $user = $this->getUser();
        $company = $user->getCompany();

        if (!$company) {
            return $this->json(['error' => 'User does not belong to a company'], Response::HTTP_BAD_REQUEST);
        }

        $assessment = $this->assessmentRepository->find($id);

        if (!$assessment) {
            return $this->json(['error' => 'Assessment not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if the assessment belongs to the user's company
        if ($assessment->getCompany()->getId() !== $company->getId()) {
            return $this->json(['error' => 'Access denied to this assessment'], Response::HTTP_FORBIDDEN);
        }

        // Récupérer le résumé avec les calculs
        $summary = [
            'assessment' => [
                'id' => $assessment->getId(),
                'name' => $assessment->getName(),
                'description' => $assessment->getDescription(),
                'year' => $assessment->getYear(),
                'status' => $assessment->getStatus(),
                'assessmentDate' => $assessment->getAssessmentDate()?->format('Y-m-d'),
                'createdAt' => $assessment->getCreatedAt()?->format('Y-m-d H:i:s'),
                'company' => [
                    'id' => $assessment->getCompany()->getId(),
                    'name' => $assessment->getCompany()->getName(),
                ],
            ],
            'totals' => [
                'totalEmissions' => round($assessment->getTotalEmissions() ?? 0, 2),
                'scope1Emissions' => round($assessment->getScope1Emissions() ?? 0, 2),
                'scope2Emissions' => round($assessment->getScope2Emissions() ?? 0, 2),
                'scope3Emissions' => round($assessment->getScope3Emissions() ?? 0, 2),
            ],
            'byScope' => $this->calculationService->getEmissionsByScope($assessment),
            'byCategory' => $this->calculationService->getEmissionsByCategory($assessment),
            'emissionsCount' => $assessment->getEmissions()->count(),
        ];

        return $this->json($summary, Response::HTTP_OK);
    }
}

