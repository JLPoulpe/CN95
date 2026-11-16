<?php

namespace App\Tests\Unit\Mail;

use App\Entity\User;
use App\Mail\AbsenceMailer;
use PHPUnit\Framework\TestCase;

class AbsenceMailerTest extends TestCase
{
    private function user(int $id, string $email = 'a@example.invalid'): User
    {
        $u = (new User())->setEmail($email);
        (new \ReflectionProperty($u, 'id'))->setValue($u, $id);

        return $u;
    }

    public function testPasDeDoublonEtSansLAbsentNiAdresseVide(): void
    {
        $absent = $this->user(1);
        $collegue = $this->user(2);
        $bureauEtMoniteur = $this->user(3);
        $sansMail = $this->user(4, '');

        $dest = AbsenceMailer::destinataires(
            $absent,
            [$absent, $collegue, $bureauEtMoniteur, $sansMail],
            [$bureauEtMoniteur, $absent, $this->user(5)],
        );

        $this->assertSame([2, 3, 5], array_map(fn (User $u) => $u->getId(), $dest));
    }
}
