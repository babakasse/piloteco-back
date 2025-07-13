# PilotEco Backend Development Guidelines

This document provides essential information for developers working on the PilotEco backend project.

## Build/Configuration Instructions

### Prerequisites
- Docker and Docker Compose
- PHP 8.4.3 or higher
- Composer

### Setting Up the Development Environment

1. **Clone the repository**

2. **Start the Docker containers**
   ```bash
   make start
   ```
   This command builds the Docker images and starts the containers in detached mode.

3. **Install dependencies**
   ```bash
   make composer c="install"
   ```

4. **Initialize the database**
   ```bash
   make init-db
   ```
   This command drops and recreates the database, runs migrations, and loads fixtures.

### Common Commands

- **Start the application**
  ```bash
  make up
  ```

- **Stop the application**
  ```bash
  make down
  ```

- **View logs**
  ```bash
  make logs
  ```

- **Access the PHP container shell**
  ```bash
  make sh
  ```

- **Clear Symfony cache**
  ```bash
  make cc
  ```

## Testing Information

### Test Configuration

The project uses PHPUnit for testing with the following configuration:
- Tests are located in the `tests/` directory
- The DAMA Doctrine Test Bundle is used to wrap tests in transactions
- The test environment is configured in `.env.test`

### Running Tests

1. **Initialize the test database**
   ```bash
   make init-test
   ```
   This command creates the test database, runs migrations, and loads fixtures.

2. **Run all tests**
   ```bash
   make test
   ```

3. **Run specific tests**
   ```bash
   make test c="tests/Path/To/TestFile.php"
   ```

4. **Run tests with coverage**
   ```bash
   make test-coverage
   ```
   This generates HTML coverage reports in `var/coverage/`.

5. **Run specific test suites**
   ```bash
   make test-unit       # Run unit tests
   make test-integration # Run integration tests
   make test-functional  # Run functional tests
   ```

### Adding New Tests

1. **Create a new test file**
    - Place controller tests in `tests/Controller/`
    - Place API tests in `tests/Api/`
    - Follow the naming convention: `*Test.php`

2. **Extend the appropriate test case**
    - For API tests, extend `ApiPlatform\Symfony\Bundle\Test\ApiTestCase`
    - For other tests, extend `Symfony\Bundle\FrameworkBundle\Test\KernelTestCase` or `PHPUnit\Framework\TestCase`

3. **Example test structure**
   ```php
   <?php
   
   namespace App\Tests\Controller;
   
   use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
   
   class MyControllerTest extends ApiTestCase
   {
       public function testSomeFunctionality(): void
       {
           $client = self::createClient();
           
           // Test setup
           
           // Make request
           $client->request('GET', '/endpoint');
           
           // Assertions
           $this->assertResponseIsSuccessful();
           $this->assertJsonContains(['key' => 'value']);
       }
   }
   ```

## Additional Development Information

### Project Structure

- `src/Controller/` - Contains API controllers
- `src/Entity/` - Contains Doctrine entities
- `src/Repository/` - Contains Doctrine repositories
- `src/DataFixtures/` - Contains data fixtures for testing
- `config/` - Contains application configuration
- `migrations/` - Contains database migrations

### API Platform

The project uses API Platform for building the REST API. Key features:
- API resources are defined in `src/Entity/` using annotations/attributes
- Custom operations can be defined in controllers
- API documentation is available at `/docs`

### Authentication

The project uses Lexik JWT Authentication Bundle for JWT-based authentication:
- Login endpoint: `/login`
- Authentication is required for most endpoints
- JWT tokens should be included in the Authorization header: `Authorization: Bearer {token}`

### Database

The project uses PostgreSQL as the database:
- Database configuration is in `.env` and `config/packages/doctrine.yaml`
- Migrations are managed with Doctrine Migrations Bundle
- Run `make init-db` to initialize the database with test data

### Code Style

- Follow PSR-12 coding standards
- Use type hints for parameters and return types
- Document public methods with PHPDoc comments
- Keep controllers thin, move business logic to services

--- 
 
# Symfony Docker (Official documentation)

[![Symfony Docker](https://raw.githubusercontent.com/dunglas/symfony-docker/main/.github/logo.png)](

A [Docker](https://www.docker.com/)-based installer and runtime for the [Symfony](https://symfony.com) web framework,
with [FrankenPHP](https://frankenphp.dev) and [Caddy](https://caddyserver.com/) inside!

![CI](https://github.com/dunglas/symfony-docker/workflows/CI/badge.svg)

## Getting Started

1. If not already done, [install Docker Compose](https://docs.docker.com/compose/install/) (v2.10+)
2. Run `docker compose build --no-cache` to build fresh images
3. Run `docker compose up --pull always -d --wait` to start the project
4. Open `https://localhost` in your favorite web browser and [accept the auto-generated TLS certificate](https://stackoverflow.com/a/15076602/1352334)
5. Run `docker compose down --remove-orphans` to stop the Docker containers.

## Features

* Production, development and CI ready
* Just 1 service by default
* Blazing-fast performance thanks to [the worker mode of FrankenPHP](https://github.com/dunglas/frankenphp/blob/main/docs/worker.md) (automatically enabled in prod mode)
* [Installation of extra Docker Compose services](docs/extra-services.md) with Symfony Flex
* Automatic HTTPS (in dev and prod)
* HTTP/3 and [Early Hints](https://symfony.com/blog/new-in-symfony-6-3-early-hints) support
* Real-time messaging thanks to a built-in [Mercure hub](https://symfony.com/doc/current/mercure.html)
* [Vulcain](https://vulcain.rocks) support
* Native [XDebug](docs/xdebug.md) integration
* Super-readable configuration

**Enjoy!**

## Docs

1. [Build options](docs/build.md)
2. [Using Symfony Docker with an existing project](docs/existing-project.md)
3. [Support for extra services](docs/extra-services.md)
4. [Deploying in production](docs/production.md)
5. [Debugging with Xdebug](docs/xdebug.md)
6. [TLS Certificates](docs/tls.md)
7. [Using a Makefile](docs/makefile.md)
8. [Troubleshooting](docs/troubleshooting.md)

## License

Symfony Docker is available under the MIT License.

## Credits

Created by [Kévin Dunglas](https://dunglas.dev), co-maintained by [Maxime Helias](https://twitter.com/maxhelias) and sponsored by [Les-Tilleuls.coop](https://les-tilleuls.coop).
