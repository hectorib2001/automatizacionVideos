<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * "/" redirige a la tabla de control, que a su vez exige sesión (ver routes/web.php).
     */
    public function test_the_application_redirects_to_login_when_not_authenticated(): void
    {
        $response = $this->get('/');
        $response->assertRedirect(route('control.index'));

        $this->get(route('control.index'))->assertRedirect(route('login'));
    }
}
