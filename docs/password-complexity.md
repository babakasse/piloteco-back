# Password Complexity Requirements

## Overview

This document describes the password complexity requirements implemented in the PilotEco Back application to ensure user account security following best practices.

## Requirements

All user passwords must meet the following complexity criteria:

1. **Minimum Length**: At least 8 characters
2. **Uppercase Letter**: At least one uppercase letter (A-Z)
3. **Lowercase Letter**: At least one lowercase letter (a-z)
4. **Digit**: At least one numeric digit (0-9)
5. **Special Character**: At least one special character (e.g., !, @, #, $, %, ^, &, *, etc.)

## Implementation

The password complexity validation is implemented using a custom Symfony validator constraint:

- **Constraint**: `App\Validator\PasswordComplexity`
- **Validator**: `App\Validator\PasswordComplexityValidator`

### Where Applied

The password complexity validation is applied in the following locations:

1. **User Entity** (`src/Entity/User.php`): Applied to the `plainPassword` property
2. **User Registration DTO** (`src/Dto/UserRegistrationRequest.php`): Applied to the `plainPassword` property

## Examples

### Valid Passwords

- `Password123!`
- `MyP@ssw0rd`
- `SecureP@ss2024`
- `C0mpl3x!Pass`
- `Test123!@#`

### Invalid Passwords

| Password | Reason for Rejection |
|----------|---------------------|
| `short1!` | Too short (less than 8 characters) |
| `password123!` | Missing uppercase letter |
| `PASSWORD123!` | Missing lowercase letter |
| `Password!` | Missing digit |
| `Password123` | Missing special character |

## Error Messages

When a password doesn't meet the requirements, users will receive one of the following error messages:

1. **Too Short**: "The password must be at least 8 characters long."
2. **Missing Complexity**: "The password must contain at least one uppercase letter, one lowercase letter, one digit, and one special character."

## Testing

The password complexity validator includes comprehensive unit tests located in:
- `tests/Unit/Validator/PasswordComplexityValidatorTest.php`

These tests cover:
- Valid passwords with all requirements
- Passwords that are too short
- Passwords missing uppercase letters
- Passwords missing lowercase letters
- Passwords missing digits
- Passwords missing special characters
- Null and empty password handling

## Security Best Practices

This implementation follows OWASP (Open Web Application Security Project) recommendations for password complexity:

- Enforces minimum length of 8 characters (OWASP recommends at least 8)
- Requires character diversity (uppercase, lowercase, digits, special characters)
- Validates passwords on both entity and DTO levels
- Properly hashes passwords using Symfony's password hasher

## Migration Guide

If you have existing users with passwords that don't meet these requirements:

1. Existing users can continue to log in with their current passwords
2. When users update their passwords, they must meet the new complexity requirements
3. Consider implementing a password reset notification for users with weak passwords

## Related Files

- `src/Validator/PasswordComplexity.php` - Constraint definition
- `src/Validator/PasswordComplexityValidator.php` - Validation logic
- `src/Entity/User.php` - User entity with validation
- `src/Dto/UserRegistrationRequest.php` - Registration DTO with validation
- `tests/Unit/Validator/PasswordComplexityValidatorTest.php` - Unit tests
