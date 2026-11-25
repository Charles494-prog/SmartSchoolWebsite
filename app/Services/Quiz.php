<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Score;
use App\Models\Submission;
use App\Support\LiveReports;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Quizzes: GIFT import/export (Moodle's plain-text question format) and automatic grading.
 *
 * Question data by type:
 *  mc / multi : ['options' => [['text', 'weight' (percent, may be negative), 'feedback'], ...]]
 *  tf         : ['answer' => bool, 'feedback_true', 'feedback_false']
 *  short      : ['answers' => [['text', 'weight'], ...]]            (not case-sensitive)
 *  numeric    : ['answers' => [['value', 'tolerance', 'weight'], ...]]
 *  matching   : ['pairs' => [['left', 'right'], ...]]
 *  essay      : []                                                   (teacher gives the points)
 */
class Quiz
{
    // ------------------------------------------------------------------ GIFT import

    /** @return array<int, array{type: string, title: ?string, text: string, data: array, feedback: ?string}> */
    public static function parseGift(string $gift): array
    {
        $gift = str_replace(["\r\n", "\r"], "\n", $gift);
        // Drop comment lines and $CATEGORY lines.
        $lines = array_filter(explode("\n", $gift), fn ($l) => ! preg_match('~^\s*(//|\$CATEGORY:)~', $l));
        $blocks = preg_split("/\n\s*\n/", trim(implode("\n", $lines)));
        $out = [];
        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }
            if ($q = self::parseGiftQuestion($block)) {
                $out[] = $q;
            }
        }

        return $out;
    }

    private static function parseGiftQuestion(string $b): ?array
    {
        $title = null;
        if (preg_match('/^::(.*?)::/s', $b, $m)) {
            $title = self::unescape(trim($m[1]));
            $b = substr($b, strlen($m[0]));
        }
        $b = preg_replace('/^\[(html|moodle|plain|markdown)\]/i', '', trim($b));
        $open = self::findUnescaped($b, '{');
        if ($open === null) {
            return ['type' => 'essay', 'title' => $title, 'text' => self::unescape(trim($b)), 'data' => [], 'feedback' => null]; // description-only: treat as essay prompt
        }
        $close = self::findUnescaped($b, '}', $open);
        if ($close === null) {
            return null;
        }
        $text = trim(substr($b, 0, $open).' '.($close + 1 < strlen($b) ? '_____ '.substr($b, $close + 1) : ''));
        $text = trim(preg_replace('/\s+_____\s*$/', '', $text));
        $ans = trim(substr($b, $open + 1, $close - $open - 1));
        $q = ['title' => $title, 'text' => self::unescape($text), 'feedback' => null];

        // General feedback: ####...
        if (($p = strpos($ans, '####')) !== false) {
            $q['feedback'] = self::unescape(trim(substr($ans, $p + 4)));
            $ans = trim(substr($ans, 0, $p));
        }

        if ($ans === '') {
            return $q + ['type' => 'essay', 'data' => []];
        }
        if (preg_match('/^(T|TRUE|F|FALSE)\b(.*)$/is', $ans, $m)) {
            $fb = self::splitTokens($m[2], '#');

            return $q + ['type' => 'tf', 'data' => ['answer' => in_array(strtoupper($m[1]), ['T', 'TRUE'], true), 'feedback_wrong' => isset($fb[1]) ? self::unescape(trim($fb[1])) : null, 'feedback_right' => isset($fb[2]) ? self::unescape(trim($fb[2])) : null]];
        }
        if (str_starts_with($ans, '#')) {
            $answers = [];
            foreach (self::answerTokens(substr($ans, 1), true) as [$mark, $weight, $body, $fb]) {
                $body = trim($body);
                if (preg_match('/^(-?[\d.]+)\.\.(-?[\d.]+)$/', $body, $r)) {
                    $answers[] = ['value' => ((float) $r[1] + (float) $r[2]) / 2, 'tolerance' => abs((float) $r[2] - (float) $r[1]) / 2, 'weight' => $weight];
                } elseif (preg_match('/^(-?[\d.]+)(?::([\d.]+))?$/', $body, $r)) {
                    $answers[] = ['value' => (float) $r[1], 'tolerance' => (float) ($r[2] ?? 0), 'weight' => $weight];
                }
            }

            return $q + ['type' => 'numeric', 'data' => ['answers' => $answers]];
        }
        $tokens = self::answerTokens($ans);
        if ($tokens && collect($tokens)->every(fn ($t) => $t[0] === '=' && str_contains($t[2], '->'))) {
            $pairs = array_map(fn ($t) => array_map(fn ($s) => self::unescape(trim($s)), explode('->', $t[2], 2)), $tokens);

            return $q + ['type' => 'matching', 'data' => ['pairs' => array_map(fn ($p) => ['left' => $p[0], 'right' => $p[1]], $pairs)]];
        }
        $hasWrong = collect($tokens)->contains(fn ($t) => $t[0] === '~');
        if (! $hasWrong) {
            return $q + ['type' => 'short', 'data' => ['answers' => array_map(fn ($t) => ['text' => self::unescape(trim($t[2])), 'weight' => $t[1]], $tokens)]];
        }
        $options = array_map(fn ($t) => ['text' => self::unescape(trim($t[2])), 'weight' => $t[1], 'feedback' => $t[3] !== null ? self::unescape(trim($t[3])) : null], $tokens);
        $full = collect($options)->where('weight', 100)->count();
        $partial = collect($options)->contains(fn ($o) => $o['weight'] > 0 && $o['weight'] < 100);
        if (! $partial && $full >= 1) {
            // Like Moodle: several "=" answers means any one of them is right (still pick one).
            return $q + ['type' => 'mc', 'data' => ['options' => $options, 'any_correct' => $full > 1]];
        }

        return $q + ['type' => 'multi', 'data' => ['options' => $options]];
    }

    /** Split "=a ~b ~%50%c#fb" into [mark, weight, body, feedback]. */
    private static function answerTokens(string $s, bool $numeric = false): array
    {
        $out = [];
        $len = strlen($s);
        $cur = null;
        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            if ($c === '\\' && $i + 1 < $len) {
                if ($cur !== null) {
                    $cur[2] .= $c.$s[$i + 1];
                }
                $i++;

                continue;
            }
            if ($c === '=' || $c === '~') {
                // '=' inside a matching answer "a -> b" is never a separator before '->'; treat = / ~ as new answer.
                if ($cur !== null) {
                    $out[] = $cur;
                }
                $cur = [$c, $c === '=' ? 100 : 0, '', null];

                continue;
            }
            if ($cur === null) {
                if ($numeric && trim($c) !== '') {
                    $cur = ['=', 100, '', null];
                } else {
                    continue;
                }
            }
            $cur[2] .= $c;
        }
        if ($cur !== null) {
            $out[] = $cur;
        }
        foreach ($out as &$t) {
            if (preg_match('/^\s*%(-?[\d.]+)%(.*)$/s', $t[2], $m)) {
                $t[1] = (float) $m[1];
                $t[2] = $m[2];
            }
            $parts = self::splitTokens($t[2], '#');
            $t[2] = $parts[0];
            $t[3] = $parts[1] ?? null;
        }

        return $out;
    }

    private static function splitTokens(string $s, string $sep): array
    {
        $parts = [''];
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            if ($s[$i] === '\\' && $i + 1 < $n) {
                $parts[count($parts) - 1] .= $s[$i].$s[++$i];
            } elseif ($s[$i] === $sep) {
                $parts[] = '';
            } else {
                $parts[count($parts) - 1] .= $s[$i];
            }
        }

        return $parts;
    }

    private static function findUnescaped(string $s, string $ch, int $from = 0): ?int
    {
        for ($i = $from, $n = strlen($s); $i < $n; $i++) {
            if ($s[$i] === '\\') {
                $i++;
            } elseif ($s[$i] === $ch) {
                return $i;
            }
        }

        return null;
    }

    private static function unescape(string $s): string
    {
        return preg_replace('/\\\\([~=#{}:\\\\])/', '$1', str_replace('\n', "\n", $s));
    }

    private static function esc(string $s): string
    {
        return addcslashes($s, '~=#{}:\\');
    }

    // ------------------------------------------------------------------ GIFT export

    public static function toGift(Assignment $a): string
    {
        $out = ['// '.$a->title.' – exported from SmartSchool', ''];
        foreach ($a->questions as $q) {
            $head = ($q->title ? '::'.self::esc($q->title).':: ' : '').self::esc($q->text).' ';
            $d = $q->data;
            $body = match ($q->type) {
                'mc', 'multi' => implode(' ', array_map(fn ($o) => ($q->type === 'mc' && $o['weight'] == 100 ? '=' : '~'.($q->type === 'multi' || ($o['weight'] != 0 && $o['weight'] != 100) ? '%'.rtrim(rtrim(number_format($o['weight'], 5, '.', ''), '0'), '.').'%' : ''))
                    .self::esc($o['text']).(! empty($o['feedback']) ? '#'.self::esc($o['feedback']) : ''), $d['options'] ?? [])),
                'tf' => ($d['answer'] ? 'TRUE' : 'FALSE'),
                'short' => implode(' ', array_map(fn ($x) => '='.(($x['weight'] ?? 100) != 100 ? '%'.$x['weight'].'%' : '').self::esc($x['text']), $d['answers'] ?? [])),
                'numeric' => '#'.implode(' ', array_map(fn ($x) => '='.(($x['weight'] ?? 100) != 100 ? '%'.$x['weight'].'%' : '').$x['value'].($x['tolerance'] ? ':'.$x['tolerance'] : ''), $d['answers'] ?? [])),
                'matching' => implode(' ', array_map(fn ($p) => '='.self::esc($p['left']).' -> '.self::esc($p['right']), $d['pairs'] ?? [])),
                default => '',
            };
            $fb = $q->feedback ? ' ####'.self::esc($q->feedback) : '';
            $out[] = $head.'{'.($body !== '' ? ' '.$body : '').$fb.($body !== '' ? ' ' : '').'}';
            $out[] = '';
        }

        return implode("\n", $out);
    }

    // ------------------------------------------------------------------ grading

    /** Points earned for one answer (null = needs the teacher, e.g. essays). */
    public static function mark(QuizQuestion $q, mixed $answer): ?float
    {
        $d = $q->data;
        $pts = (float) $q->points;
        $frac = match ($q->type) {
            'mc' => (float) (($d['options'][(int) $answer]['weight'] ?? 0) / 100),
            'multi' => ! empty($d['all_or_nothing'])
                // All-or-nothing: full points only when exactly the right boxes are ticked.
                ? (collect((array) $answer)->map(fn ($i) => (int) $i)->sort()->values()->all() === collect($d['options'] ?? [])->filter(fn ($o) => $o['weight'] > 0)->keys()->sort()->values()->all() ? 1 : 0)
                : max(0, array_sum(array_map(fn ($i) => (float) ($d['options'][(int) $i]['weight'] ?? 0), (array) $answer)) / 100),
            'tf' => $answer === null || $answer === '' ? 0 : ((filter_var($answer, FILTER_VALIDATE_BOOLEAN) === (bool) $d['answer']) ? 1 : 0),
            'short' => (float) (collect($d['answers'] ?? [])->filter(fn ($x) => Str::lower(trim((string) $x['text'])) === Str::lower(trim((string) $answer)))->max('weight') ?? 0) / 100,
            'numeric' => is_numeric($answer) ? (float) (collect($d['answers'] ?? [])->filter(fn ($x) => abs((float) $answer - (float) $x['value']) <= (float) $x['tolerance'] + 1e-9)->max('weight') ?? 0) / 100 : 0,
            'matching' => ($n = count($d['pairs'] ?? [])) ? collect($d['pairs'])->filter(fn ($p, $i) => ($answer[$i] ?? null) === $p['right'])->count() / $n : 0,
            default => null,
        };

        return $frac === null ? null : round(min(1, $frac) * $pts, 2);
    }

    /** Grade a finished attempt; then copy the best score to the submission and the gradebook. */
    public static function finish(QuizAttempt $attempt): QuizAttempt
    {
        $a = $attempt->assignment;
        $questions = $a->questions()->get()->keyBy('id');
        $marks = [];
        $review = false;
        foreach ($questions as $id => $q) {
            $m = self::mark($q, $attempt->answers[$id] ?? null);
            $review = $review || $m === null;
            $marks[$id] = $m;
        }
        $attempt->update(['marks' => $marks, 'needs_review' => $review, 'finished_at' => $attempt->finished_at ?? now(), 'score' => self::scale($a, array_sum(array_filter($marks)))]);
        self::syncBest($a, $attempt->student_id);

        return $attempt;
    }

    /** Raw quiz points → the assignment's points (e.g. 12 of 15 questions → 16 of 20). */
    public static function scale(Assignment $a, float $raw): float
    {
        $total = (float) $a->questions()->sum('points');

        return $total > 0 ? round($raw / $total * (float) $a->max_score, 2) : 0;
    }

    public static function syncBest(Assignment $a, int $studentId): void
    {
        $best = QuizAttempt::where('assignment_id', $a->id)->where('student_id', $studentId)->whereNotNull('finished_at')->orderByDesc('score')->first();
        if (! $best) {
            return;
        }
        DB::transaction(function () use ($a, $studentId, $best) {
            $sub = Submission::firstOrNew(['assignment_id' => $a->id, 'student_id' => $studentId]);
            $sub->fill(['body' => 'Quiz attempt '.$best->attempt_no, 'submitted_at' => $best->finished_at, 'score' => $best->needs_review ? null : $best->score])->save();
            if (! $best->needs_review) {
                LiveReports::send($best->student, $a->title.' (quiz)', $best->score.'/'.$a->max_score);
            }
            if ($a->assessment_id && ! $best->needs_review && ! $a->classSubject->isLocked((int) $a->term)) {
                Score::updateOrCreate(['assessment_id' => $a->assessment_id, 'student_id' => $studentId], ['score' => $best->score]);
            }
        });
    }
}
