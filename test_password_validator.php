<?php

// Simple validation test script
require_once __DIR__ . '/vendor/autoload.php';

use App\Validator\PasswordComplexity;
use App\Validator\PasswordComplexityValidator;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;

echo "Testing Password Complexity Validator\n";
echo "======================================\n\n";

$validator = Validation::createValidator();

// Test data
$testPasswords = [
    // Valid passwords
    ['password' => 'Password123!', 'expected' => 'valid', 'description' => 'Valid password with all requirements'],
    ['password' => 'MyP@ssw0rd', 'expected' => 'valid', 'description' => 'Valid password with mixed case'],
    ['password' => 'C0mpl3x!Pass', 'expected' => 'valid', 'description' => 'Valid complex password'],
    
    // Invalid passwords
    ['password' => 'short1!', 'expected' => 'invalid', 'description' => 'Too short (7 chars)'],
    ['password' => 'password123!', 'expected' => 'invalid', 'description' => 'Missing uppercase'],
    ['password' => 'PASSWORD123!', 'expected' => 'invalid', 'description' => 'Missing lowercase'],
    ['password' => 'Password!', 'expected' => 'invalid', 'description' => 'Missing digit'],
    ['password' => 'Password123', 'expected' => 'invalid', 'description' => 'Missing special character'],
];

foreach ($testPasswords as $test) {
    $violations = $validator->validate($test['password'], [
        new Assert\NotBlank(),
        new PasswordComplexity()
    ]);
    
    $isValid = count($violations) === 0;
    $status = $isValid ? '✓ VALID' : '✗ INVALID';
    $expected = $test['expected'] === 'valid' ? '✓ VALID' : '✗ INVALID';
    $match = ($isValid && $test['expected'] === 'valid') || (!$isValid && $test['expected'] === 'invalid');
    
    echo sprintf(
        "[%s] %s: '%s' - %s (Expected: %s)\n",
        $match ? 'PASS' : 'FAIL',
        $status,
        $test['password'],
        $test['description'],
        $expected
    );
    
    if (!$isValid && count($violations) > 0) {
        foreach ($violations as $violation) {
            echo "    → " . $violation->getMessage() . "\n";
        }
    }
}

echo "\nTest completed!\n";
