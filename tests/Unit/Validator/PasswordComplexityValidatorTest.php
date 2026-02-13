<?php

namespace App\Tests\Unit\Validator;

use App\Validator\PasswordComplexity;
use App\Validator\PasswordComplexityValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class PasswordComplexityValidatorTest extends TestCase
{
    private PasswordComplexityValidator $validator;
    private ExecutionContextInterface $context;
    private ConstraintViolationBuilderInterface $builder;

    protected function setUp(): void
    {
        $this->validator = new PasswordComplexityValidator();
        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        
        $this->validator->initialize($this->context);
    }

    public function testValidPasswordWithAllRequirements(): void
    {
        $constraint = new PasswordComplexity();
        
        // A valid password: uppercase, lowercase, digit, special char, 8+ chars
        $validPassword = 'Password123!';
        
        $this->context->expects($this->never())
            ->method('buildViolation');
        
        $this->validator->validate($validPassword, $constraint);
    }

    public function testPasswordTooShort(): void
    {
        $constraint = new PasswordComplexity();
        
        $shortPassword = 'Pwd1!';
        
        $this->builder->expects($this->once())
            ->method('setParameter')
            ->with('{{ limit }}', '8')
            ->willReturnSelf();
        
        $this->builder->expects($this->once())
            ->method('addViolation');
        
        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with($constraint->tooShortMessage)
            ->willReturn($this->builder);
        
        $this->validator->validate($shortPassword, $constraint);
    }

    public function testPasswordMissingUppercase(): void
    {
        $constraint = new PasswordComplexity();
        
        $password = 'password123!';
        
        $this->builder->expects($this->once())
            ->method('addViolation');
        
        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with($constraint->message)
            ->willReturn($this->builder);
        
        $this->validator->validate($password, $constraint);
    }

    public function testPasswordMissingLowercase(): void
    {
        $constraint = new PasswordComplexity();
        
        $password = 'PASSWORD123!';
        
        $this->builder->expects($this->once())
            ->method('addViolation');
        
        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with($constraint->message)
            ->willReturn($this->builder);
        
        $this->validator->validate($password, $constraint);
    }

    public function testPasswordMissingDigit(): void
    {
        $constraint = new PasswordComplexity();
        
        $password = 'Password!';
        
        $this->builder->expects($this->once())
            ->method('addViolation');
        
        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with($constraint->message)
            ->willReturn($this->builder);
        
        $this->validator->validate($password, $constraint);
    }

    public function testPasswordMissingSpecialCharacter(): void
    {
        $constraint = new PasswordComplexity();
        
        $password = 'Password123';
        
        $this->builder->expects($this->once())
            ->method('addViolation');
        
        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with($constraint->message)
            ->willReturn($this->builder);
        
        $this->validator->validate($password, $constraint);
    }

    public function testNullPasswordIsAllowed(): void
    {
        $constraint = new PasswordComplexity();
        
        $this->context->expects($this->never())
            ->method('buildViolation');
        
        $this->validator->validate(null, $constraint);
    }

    public function testEmptyPasswordIsAllowed(): void
    {
        $constraint = new PasswordComplexity();
        
        $this->context->expects($this->never())
            ->method('buildViolation');
        
        $this->validator->validate('', $constraint);
    }

    public function testVariousValidPasswords(): void
    {
        $constraint = new PasswordComplexity();
        
        $validPasswords = [
            'Abcdefg1!',
            'SecureP@ss123',
            'MyP@ssw0rd',
            'Test123!@#',
            'C0mpl3x!Pass',
        ];
        
        foreach ($validPasswords as $password) {
            // Create a fresh context for each password test
            $context = $this->createMock(ExecutionContextInterface::class);
            $context->expects($this->never())
                ->method('buildViolation');
            
            $validator = new PasswordComplexityValidator();
            $validator->initialize($context);
            $validator->validate($password, $constraint);
        }
    }
}
