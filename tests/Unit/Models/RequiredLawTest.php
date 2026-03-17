<?php

namespace Tests\Unit\Models;

use App\Models\LegalCase;
use App\Models\RequiredLaw;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequiredLawTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_case(): void
    {
        $law = RequiredLaw::factory()->create();
        $this->assertInstanceOf(LegalCase::class, $law->case);
    }

    public function test_is_uploaded_cast_to_boolean(): void
    {
        $law = RequiredLaw::factory()->create(['is_uploaded' => true]);
        $this->assertTrue($law->is_uploaded);

        $law = RequiredLaw::factory()->create(['is_uploaded' => false]);
        $this->assertFalse($law->is_uploaded);
    }
}
