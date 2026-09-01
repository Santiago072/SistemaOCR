<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Security;

class SecurityTest extends TestCase
{
    public function testGenerateCsrfToken(): void
    {
        $token = Security::generateCsrfToken();
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));
    }

    public function testSanitizeInput(): void
    {
        $dirty = "<script>alert('xss');</script> Juan & Pedro";
        $clean = Security::sanitizeInput($dirty);
        
        $this->assertStringNotContainsString("<script>", $clean);
        $this->assertStringContainsString("Juan", $clean);
        $this->assertStringContainsString("Pedro", $clean);
    }

    public function testCsrfTokenValidation(): void
    {
        $token = Security::generateCsrfToken();
        $this->assertTrue(Security::validateCsrfToken($token));
        $this->assertFalse(Security::validateCsrfToken('token_falso_invalido'));
    }
}
