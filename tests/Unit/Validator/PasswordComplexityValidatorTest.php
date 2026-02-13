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

    /**
     * @dataProvider validPasswordProvider
     */
    public function testVariousValidPasswords(string $password): void
    {
        $constraint = new PasswordComplexity();
        
        $this->context->expects($this->never())
            ->method('buildViolation');
        
        $this->validator->validate($password, $constraint);
    }
    
    public static function validPasswordProvider(): array
    {
        return [
            'minimum valid password' => ['Abcdefg1!'],
            'password with multiple special chars' => ['SecureP@ss123'],
            'password with @ symbol' => ['MyP@ssw0rd'],
            'password with multiple special chars at end' => ['Test123!@#'],
            'complex password with numbers' => ['C0mpl3x!Pass'],
        ];
    }
}
