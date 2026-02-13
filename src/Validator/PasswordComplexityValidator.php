<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class PasswordComplexityValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PasswordComplexity) {
            throw new UnexpectedTypeException($constraint, PasswordComplexity::class);
        }

        // null and empty values are allowed (use NotBlank constraint separately)
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        // Check minimum length
        if (mb_strlen($value) < $constraint->minLength) {
            $this->context->buildViolation($constraint->tooShortMessage)
                ->setParameter('{{ limit }}', (string) $constraint->minLength)
                ->addViolation();
            return;
        }

        // Check for at least one uppercase letter
        if (!preg_match('/[A-Z]/', $value)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Check for at least one lowercase letter
        if (!preg_match('/[a-z]/', $value)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Check for at least one digit
        if (!preg_match('/[0-9]/', $value)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Check for at least one special character
        if (!preg_match('/[^A-Za-z0-9]/', $value)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }
    }
}
