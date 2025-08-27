<?php

namespace App\Command;

use App\DataFixtures\DemoFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LoadDemoDataCommand extends Command
{
    protected static $defaultName = 'app:load-demo-data';
    protected static $defaultDescription = 'Charge les données de démonstration pour la production';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct(static::$defaultName);
    }

    protected function configure(): void
    {
        $this
            ->setName('app:load-demo-data')
            ->setDescription('Charge les données de démonstration sécurisées pour la production')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force le chargement même si des données existent déjà')
            ->addOption('no-interaction', null, InputOption::VALUE_NONE, 'Mode non interactif')
            ->setHelp('Cette commande charge des données de démonstration réalistes et sécurisées pour les environnements de production.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Vérification de l'environnement avec support pour Azure
        $isProduction = $_ENV['APP_ENV'] === 'prod';
        $isDemoEnv = $_ENV['APP_ENV'] === 'demo';
        $isForced = $input->getOption('force');
        $isNonInteractive = $input->getOption('no-interaction');

        if ($isProduction && !$isForced && !$isDemoEnv && !$isNonInteractive) {
            $io->warning('Vous êtes en environnement de production.');
            if (!$io->confirm('Êtes-vous sûr de vouloir charger les données de démonstration ?', false)) {
                $io->info('Opération annulée.');
                return Command::SUCCESS;
            }
        }

        // Vérification si des données existent déjà (sauf en mode non-interactif)
        $userCount = $this->entityManager->getRepository(\App\Entity\User::class)->count([]);
        $companyCount = $this->entityManager->getRepository(\App\Entity\Company::class)->count([]);

        if (($userCount > 0 || $companyCount > 0) && !$isForced && !$isNonInteractive) {
            $io->warning("Des données existent déjà dans la base de données ($userCount utilisateurs, $companyCount entreprises).");
            if (!$io->confirm('Voulez-vous continuer ? Cela ajoutera les données de démonstration aux données existantes.', false)) {
                $io->info('Opération annulée.');
                return Command::SUCCESS;
            }
        }

        $io->title('Chargement des données de démonstration');

        // Affichage du contexte de déploiement
        if ($isNonInteractive) {
            $io->note('Mode automatique - Déploiement Azure Web App');
        }

        try {
            // Chargement des fixtures de démonstration
            $demoFixtures = new DemoFixtures($this->passwordHasher);
            $demoFixtures->load($this->entityManager);

            $io->success('Données de démonstration chargées avec succès !');

            $io->section('Comptes de démonstration créés :');
            $io->table(
                ['Email', 'Mot de passe', 'Rôle', 'Entreprise'],
                [
                    ['demo.admin@piloteco.fr', 'DemoPassword2024!', 'ADMIN', 'EcoTech Solutions'],
                    ['demo.manager@ecotech.fr', 'DemoPassword2024!', 'USER', 'EcoTech Solutions'],
                    ['demo.analyst@greenmanuf.fr', 'DemoPassword2024!', 'USER', 'Green Manufacturing Co.'],
                    ['demo.consultant@sustainable.fr', 'DemoPassword2024!', 'USER', 'Sustainable Logistics'],
                    ['demo.expert@cleanenergy.fr', 'DemoPassword2024!', 'USER', 'CleanEnergy Corp'],
                ]
            );

            if ($isNonInteractive) {
                $io->note([
                    'Déploiement Azure - Données de démonstration prêtes :',
                    '• 5 entreprises avec secteurs variés',
                    '• 5 utilisateurs avec rôles différents',
                    '• 4 évaluations carbone complètes',
                    '• Émissions réalistes par scope',
                    '',
                    'Application prête pour les démonstrations client.'
                ]);
            } else {
                $io->note([
                    'Les données incluent :',
                    '• 5 entreprises de démonstration avec des secteurs variés',
                    '• 5 utilisateurs avec des rôles différents',
                    '• 4 évaluations carbone (2 publiées, 2 en brouillon)',
                    '• Des émissions réalistes par catégorie et scope',
                    '',
                    'Ces comptes peuvent être utilisés pour les démonstrations en production.'
                ]);
            }

        } catch (\Exception $e) {
            $io->error('Erreur lors du chargement des données de démonstration : ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
