<?php

namespace App\Support;

use App\Models\QuizQuestion;

/** Human-readable answers for quiz review screens. */
class QuizView
{
    public static function answerText(QuizQuestion $q, mixed $a): string
    {
        $d = $q->data;
        if ($a === null || $a === '' || $a === []) {
            return '(no answer)';
        }

        return match ($q->type) {
            'mc' => $d['options'][(int) $a]['text'] ?? '?',
            'multi' => implode(', ', array_map(fn ($i) => $d['options'][(int) $i]['text'] ?? '?', (array) $a)),
            'tf' => filter_var($a, FILTER_VALIDATE_BOOLEAN) ? 'True' : 'False',
            'matching' => implode('; ', array_map(fn ($p, $i) => $p['left'].' → '.(((array) $a)[$i] ?? '—'), $d['pairs'] ?? [], array_keys($d['pairs'] ?? []))),
            default => (string) (is_array($a) ? implode(', ', $a) : $a),
        };
    }

    public static function correctText(QuizQuestion $q): string
    {
        $d = $q->data;

        return match ($q->type) {
            'mc', 'multi' => implode(', ', array_map(fn ($o) => $o['text'], array_filter($d['options'] ?? [], fn ($o) => $o['weight'] > 0))),
            'tf' => $d['answer'] ? 'True' : 'False',
            'short' => implode(' / ', array_map(fn ($x) => $x['text'], array_filter($d['answers'] ?? [], fn ($x) => ($x['weight'] ?? 100) >= 100))),
            'numeric' => implode(' / ', array_map(fn ($x) => $x['value'].($x['tolerance'] ? ' ± '.$x['tolerance'] : ''), $d['answers'] ?? [])),
            'matching' => implode('; ', array_map(fn ($p) => $p['left'].' → '.$p['right'], $d['pairs'] ?? [])),
            default => '',
        };
    }
}
