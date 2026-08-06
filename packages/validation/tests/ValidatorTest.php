<?php

namespace Engine\Validation\Tests;

use Engine\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testRequiredFailsOnEmpty(): void
    {
        $v = Validator::make(['email' => ''], ['email' => 'required']);

        $this->assertTrue($v->fails());
        $this->assertSame('email est requis.', $v->error('email'));
    }

    public function testRequiredPassesOnValue(): void
    {
        $v = Validator::make(['email' => 'demo@example.com'], ['email' => 'required']);

        $this->assertTrue($v->passes());
        $this->assertNull($v->error('email'));
    }

    public function testEmailRule(): void
    {
        $this->assertTrue(Validator::make(['email' => 'not-an-email'], ['email' => 'email'])->fails());
        $this->assertTrue(Validator::make(['email' => 'demo@example.com'], ['email' => 'email'])->passes());
    }

    public function testNonRequiredRuleSkipsEmptyValue(): void
    {
        // 'email' alone (no 'required') on an untouched optional field
        // shouldn't fail just because it's blank — only 'required' cares
        // about emptiness itself.
        $v = Validator::make(['newsletter_email' => ''], ['newsletter_email' => 'email']);

        $this->assertTrue($v->passes());
    }

    public function testMinAndMax(): void
    {
        $rules = ['password' => 'min:8|max:20'];

        $this->assertTrue(Validator::make(['password' => 'short'], $rules)->fails());
        $this->assertTrue(Validator::make(['password' => str_repeat('a', 30)], $rules)->fails());
        $this->assertTrue(Validator::make(['password' => 'goodpassword'], $rules)->passes());
    }

    public function testSameRuleForPasswordConfirmation(): void
    {
        $rules = ['password_confirmation' => 'same:password'];

        $this->assertTrue(Validator::make(['password' => 'secret123', 'password_confirmation' => 'secret123'], $rules)->passes());
        $this->assertTrue(Validator::make(['password' => 'secret123', 'password_confirmation' => 'nope'], $rules)->fails());
    }

    public function testInRule(): void
    {
        $rules = ['plan' => 'in:free,pro,team'];

        $this->assertTrue(Validator::make(['plan' => 'pro'], $rules)->passes());
        $this->assertTrue(Validator::make(['plan' => 'enterprise'], $rules)->fails());
    }

    public function testFirstFailingRuleWinsPerField(): void
    {
        // 'min:20' would also fail here, but 'email' is listed first —
        // exactly one message per field, not every violation stacked up.
        $v = Validator::make(['email' => 'not-an-email'], ['email' => 'email|min:20']);

        $this->assertSame('email doit être une adresse email valide.', $v->error('email'));
    }

    public function testMultipleFieldsIndependently(): void
    {
        $v = Validator::make(
            ['username' => 'demo', 'password' => ''],
            ['username' => 'required|min:3', 'password' => 'required'],
        );

        $this->assertTrue($v->fails());
        $this->assertNull($v->error('username'));
        $this->assertSame('password est requis.', $v->error('password'));
        $this->assertCount(1, $v->errors());
    }

    public function testValidatedReturnsOnlyRuledFieldsTrimmed(): void
    {
        $v = Validator::make(
            ['username' => '  demo  ', 'password' => 'secret', 'unrelated' => 'ignored'],
            ['username' => 'required', 'password' => 'required'],
        );

        $this->assertSame(['username' => 'demo', 'password' => 'secret'], $v->validated());
    }

    public function testRuleArraySyntax(): void
    {
        $v = Validator::make(['email' => ''], ['email' => ['required', 'email']]);

        $this->assertTrue($v->fails());
    }
}
