<?php

namespace App\Tests\Unit\Export;

use App\Export\CsvSecurise;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvSecuriseTest extends TestCase
{
    #[DataProvider('dangereuses')]
    public function testNeutraliseLesFormules(string $valeur): void
    {
        $this->assertSame("'".$valeur, CsvSecurise::cellule($valeur));
    }

    /** @return iterable<string, array{string}> */
    public static function dangereuses(): iterable
    {
        yield 'égal' => ['=HYPERLINK("http://x","clic")'];
        yield 'plus' => ['+33612345678'];
        yield 'moins' => ['-2+3'];
        yield 'arobase' => ['@SUM(A1)'];
        yield 'tabulation' => ["\t=1+1"];
        yield 'espace puis formule' => [' =1+1'];
        yield 'espace insécable puis formule' => ["\u{00A0}=1+1"];
    }

    public function testLaissePasserLesValeursNormales(): void
    {
        $this->assertSame('DUPONT', CsvSecurise::cellule('DUPONT'));
        $this->assertSame('06 12 34 56 78', CsvSecurise::cellule('06 12 34 56 78'));
        $this->assertSame('Jean-Pierre', CsvSecurise::cellule('Jean-Pierre'));
        $this->assertSame('', CsvSecurise::cellule(''));
        $this->assertSame('', CsvSecurise::cellule(null));
        $this->assertSame(3, CsvSecurise::cellule(3));
    }

    public function testLigne(): void
    {
        $this->assertSame([1, "'=1+1", 'Marie', ''], CsvSecurise::ligne([1, '=1+1', 'Marie', null]));
    }
}
