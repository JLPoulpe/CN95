<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserActivationTest extends TestCase
{
    public function testJetonValideDansLesSeptJours(): void
    {
        $user = new User();
        $maintenant = new \DateTimeImmutable('2026-10-11 10:00');
        $jeton = $user->genererActivation($maintenant);

        $this->assertTrue($user->activationEnAttente());
        $this->assertTrue($user->activationValide($jeton, $maintenant->modify('+6 days')));
    }

    public function testJetonExpireApresSeptJours(): void
    {
        $user = new User();
        $maintenant = new \DateTimeImmutable('2026-10-11 10:00');
        $jeton = $user->genererActivation($maintenant);

        $this->assertFalse($user->activationValide($jeton, $maintenant->modify('+8 days')));
    }

    public function testMauvaisJetonRefuse(): void
    {
        $user = new User();
        $maintenant = new \DateTimeImmutable();
        $user->genererActivation($maintenant);

        $this->assertFalse($user->activationValide('0', $maintenant));
    }

    public function testNouveauJetonInvalideLAncien(): void
    {
        $user = new User();
        $maintenant = new \DateTimeImmutable();
        $ancien = $user->genererActivation($maintenant);
        $nouveau = $user->genererActivation($maintenant);

        $this->assertFalse($user->activationValide($ancien, $maintenant));
        $this->assertTrue($user->activationValide($nouveau, $maintenant));
    }

    public function testActivationTermineeEfface(): void
    {
        $user = new User();
        $maintenant = new \DateTimeImmutable();
        $jeton = $user->genererActivation($maintenant);

        $user->terminerActivation('hash');

        $this->assertFalse($user->activationEnAttente());
        $this->assertFalse($user->activationValide($jeton, $maintenant));
        $this->assertSame('hash', $user->getPassword());
    }
}
