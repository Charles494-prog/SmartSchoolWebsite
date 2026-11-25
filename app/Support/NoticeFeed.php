<?php

namespace App\Support;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Notification list helpers: type of each notice, and collapsing near-identical notices about the same person. */
class NoticeFeed
{
    public const FILTERS = ['all' => 'All', 'unread' => 'Unread', 'request' => 'Requests', 'payment' => 'Payments'];

    public const NOUNS = ['request' => 'document request', 'payment' => 'payment update', 'grade' => 'grade update', 'order' => 'bookstore update', 'announcement' => 'announcement', 'message' => 'message', 'other' => 'notice'];

    public static function type(DatabaseNotification $n): string
    {
        $t = (string) ($n->data['title'] ?? '');
        $url = (string) ($n->data['url'] ?? '');

        return match (true) {
            Str::startsWith($t, ['New request', 'Request update', 'Your document', 'Paid – ready']) && ! Str::contains($n->data['body'] ?? '', 'Bookstore order') => 'request',
            Str::startsWith($t, ['Payment', 'Online payment', 'Order approved – please pay']) => 'payment',
            Str::startsWith($t, ['Grades submitted', 'New score', 'Work graded']) || Str::contains($url, ['gradebook', 'portal/grades']) => 'grade',
            Str::contains($t, ['order', 'Order']) || Str::contains($url, 'shop') => 'order',
            Str::startsWith($t, 'Message from') => 'message',
            Str::contains($url, 'announcements') => 'announcement',
            default => 'other',
        };
    }

    /** The person a notice is about, when it names one (e.g. "New request: TOR" → body is the student's name). */
    public static function person(DatabaseNotification $n): ?string
    {
        $t = (string) ($n->data['title'] ?? '');
        $b = (string) ($n->data['body'] ?? '');
        if (Str::startsWith($t, 'New request')) {
            return trim($b) ?: null;
        }
        if (preg_match('/ for ([^.(–]+?)( is paid| \(|\.|$)/u', $b, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /** "Today" / "Yesterday" / "Earlier" */
    public static function bucket(Carbon $at): string
    {
        return $at->isToday() ? 'Today' : ($at->isYesterday() ? 'Yesterday' : 'Earlier');
    }

    /**
     * Group a page of notices by day bucket, then collapse ones of the same type about the same person.
     *
     * @return Collection<string, Collection<int, array{key: string, type: string, items: Collection, summary: ?string}>>
     */
    public static function grouped(Collection $list): Collection
    {
        return $list->groupBy(fn ($n) => self::bucket($n->created_at))->map(function ($day) {
            return $day->groupBy(fn ($n) => ($p = self::person($n)) ? self::type($n).'|'.$p : 'single|'.$n->id)
                ->map(function ($items, $key) {
                    $first = $items->first();
                    $type = self::type($first);
                    $summary = null;
                    if ($items->count() > 1) {
                        $who = Str::after($key, '|');
                        $summary = $type === 'request'
                            ? "$who requested ".$items->count().' documents'
                            : "$who – ".$items->count().' '.Str::plural(self::NOUNS[$type], $items->count());
                    }

                    return ['key' => $key, 'type' => $type, 'items' => $items->values(), 'summary' => $summary];
                })->values();
        });
    }
}
