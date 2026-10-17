<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_routes_are_available_for_the_existing_registration_flow(): void
    {
        $this->get('/register')->assertOk();
        $this->post('/register', [])->assertSessionHasErrors();
    }

    public function test_login_page_no_longer_promotes_public_signup(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSeeText('Sign in to your account')
            ->assertDontSee('Sign up');
    }
}
