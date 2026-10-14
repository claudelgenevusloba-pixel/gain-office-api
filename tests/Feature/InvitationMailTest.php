<?php

namespace Tests\Feature;

use App\Mail\InvitationEmploye;
use Tests\TestCase;

class InvitationMailTest extends TestCase
{
    public function test_invitation_email_button_uses_the_activation_url(): void
    {
        $activationUrl = 'https://example.test/activation-compte?token=diagnostic-token';
        $email = new InvitationEmploye(
            'Utilisateur de test',
            $activationUrl,
            '01/01/2099'
        );

        $html = $email->render();

        $this->assertStringContainsString(
            'href="' . $activationUrl . '"',
            $html
        );
    }
}