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