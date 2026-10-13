<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('staff.dashboard'))->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_staff_area(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('staff.dashboard'))
            ->assertForbidden();
    }

    public function test_staff_can_visit_the_dashboard(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('staff.dashboard'))
            ->assertOk();
    }
}
