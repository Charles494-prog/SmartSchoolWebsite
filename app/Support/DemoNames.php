<?php

namespace App\Support;

/** Demo-only name generator: every number gets a different full name, and first names match the student's sex. */
class DemoNames
{
    public const MALE = ['Juan', 'Jose', 'Mark', 'Paolo', 'Carlo', 'Miguel', 'Rafael', 'Luis', 'Gabriel', 'Daniel', 'Adrian', 'Benedict', 'Christian', 'Dominic', 'Elijah',
        'Francis', 'Gerald', 'Harold', 'Ivan', 'Joshua', 'Kevin', 'Lorenzo', 'Marco', 'Nathan', 'Oliver', 'Patrick', 'Ramon', 'Samuel', 'Tristan', 'Vincent'];

    public const FEMALE = ['Maria', 'Ana', 'Angel', 'Jasmine', 'Bea', 'Sofia', 'Andrea', 'Nicole', 'Patricia', 'Kristine', 'Alyssa', 'Bianca', 'Camille', 'Denise', 'Erica',
        'Faith', 'Gwen', 'Hannah', 'Isabel', 'Janelle', 'Katrina', 'Leah', 'Mika', 'Nadine', 'Olivia', 'Pia', 'Queenie', 'Rica', 'Stephanie', 'Trisha'];

    public const LAST = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Mendoza', 'Torres', 'Flores', 'Ramos', 'Gonzales', 'Lopez', 'Castillo', 'Villanueva', 'Rivera', 'Aquino',
        'Dela Cruz', 'Fernandez', 'Pascual', 'Navarro', 'Aguilar', 'Domingo', 'Salvador', 'Morales', 'Valdez', 'Gutierrez', 'Mercado', 'Del Rosario', 'Soriano', 'Manalo', 'Ocampo',
        'Tolentino', 'Galang', 'Rosales', 'Padilla', 'Javier', 'Cabrera', 'Lim', 'Tan', 'Sison', 'Yap'];

    /** @return array{0: string, 1: string, 2: string} first, middle, last – unique for n < 2400 */
    public static function for(int $n): array
    {
        $female = $n % 2 === 1;
        $i = intdiv($n, 2);
        $firsts = $female ? self::FEMALE : self::MALE;
        $f = count($firsts);
        $l = count(self::LAST);
        $last = self::LAST[($i + intdiv($i, $f)) % $l];
        $middle = self::LAST[($i * 7 + 3) % $l];
        if ($middle === $last) {
            $middle = self::LAST[($i * 7 + 4) % $l];
        }

        return [$firsts[$i % $f], $middle, $last];
    }
}
