<?php

namespace Tests\Unit;

use App\Services\Grading;
use App\Services\Payroll;
use PHPUnit\Framework\TestCase;

class GradingTest extends TestCase
{
    public function test_deped_transmutation_table(): void
    {
        $table = [[100, 100], [99.99, 99], [98.40, 99], [98.39, 98], [96.80, 98], [92, 95], [90.40, 94], [84, 90], [80.80, 88], [68, 80], [61.60, 76], [60, 75], [59.99, 74], [56, 74], [55.99, 73], [20, 65], [4, 61], [3.99, 60], [0, 60]];
        foreach ($table as [$initial, $expected]) {
            $this->assertSame($expected, Grading::transmute((float) $initial), "initial $initial");
        }
    }

    public function test_initial_grade_uses_weights(): void
    {
        // WW 40/50 = 80%, PT 45/50 = 90%, QA 30/40 = 75%  →  80*.3 + 90*.5 + 75*.2 = 84
        $ig = Grading::initialGrade(['ww' => [40, 50], 'pt' => [45, 50], 'qa' => [30, 40]], ['ww' => 30, 'pt' => 50, 'qa' => 20]);
        $this->assertSame(84.0, $ig);
        $this->assertSame(90, Grading::transmute($ig));
    }

    public function test_missing_component_scales_remaining_weights(): void
    {
        $ig = Grading::initialGrade(['ww' => [8, 10]], ['ww' => 30, 'pt' => 50, 'qa' => 20]);
        $this->assertSame(80.0, $ig);
        $this->assertNull(Grading::initialGrade([], ['ww' => 30, 'pt' => 50, 'qa' => 20]));
    }

    public function test_attendance_as_a_component(): void
    {
        // quizzes 80%, projects 90%, exams 75%, attendance 9 of 10 days = 90% with 20/40/30/10 weights
        $ig = Grading::initialGrade(['ww' => [8, 10], 'pt' => [9, 10], 'qa' => [15, 20], 'at' => [9, 10]], ['ww' => 20, 'pt' => 40, 'qa' => 30, 'at' => 10]);
        $this->assertSame(83.5, $ig);
    }

    public function test_final_grade_and_descriptor(): void
    {
        $this->assertSame(87.0, Grading::finalGrade([1 => 85, 2 => 86, 3 => 88, 4 => 89]));
        $this->assertNull(Grading::finalGrade([1 => 85, 2 => null, 3 => null, 4 => null]));
        $this->assertSame('Outstanding', Grading::descriptor(90));
        $this->assertSame('Fairly Satisfactory', Grading::descriptor(75));
        $this->assertSame('Did Not Meet Expectations', Grading::descriptor(74));
    }

    public function test_withholding_tax_brackets(): void
    {
        $this->assertSame(0.0, Payroll::withholding(20833));
        $this->assertSame(150.0, Payroll::withholding(21833));
        $this->assertSame(1875.0, Payroll::withholding(33333));
        $this->assertSame(8541.80, Payroll::withholding(66667));
        $this->assertSame(1750.0, Payroll::sss(80000));
        $this->assertSame(200.0, Payroll::pagibig(30000));
    }
}
