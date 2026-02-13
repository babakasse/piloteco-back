<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class PasswordComplexity extends Constraint
{
    public string $message = 'The password must contain at least one uppercase letter, one lowercase letter, one digit, and one special character.';
    public int $minLength = 8;
    public string $tooShortMessage = 'The password must be at least {{ limit }} characters long.';
}
