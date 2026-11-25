<?php

namespace App\Support;

use App\Models\ApplicationDocument;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * The documents families upload with an online application. The admin chooses the list
 * and which ones are required (Settings and the setup wizard).
 */
class Admission
{
    /** @return array<string, array{label: string, required: bool}> */
    public static function documents(): array
    {
        $saved = json_decode((string) Setting::get('application_documents'), true);
        if (! is_array($saved)) {
            return collect(ApplicationDocument::TYPES)->map(fn ($label) => ['label' => $label, 'required' => false])->all();
        }

        return collect($saved)
            ->filter(fn ($d) => is_array($d) && filled($d['key'] ?? null) && filled($d['label'] ?? null))
            ->mapWithKeys(fn ($d) => [(string) $d['key'] => ['label' => (string) $d['label'], 'required' => (bool) ($d['required'] ?? false)]])
            ->all();
    }

    /** Label for any document type, including ones removed from the list later. */
    public static function label(string $key): string
    {
        return self::documents()[$key]['label'] ?? ApplicationDocument::TYPES[$key] ?? Str::headline($key);
    }

    /** Validation rules for the editor (input name "docs"). */
    public static function rules(): array
    {
        return ['docs' => 'array|max:20', 'docs.*.label' => 'nullable|string|max:80', 'docs.*.key' => 'nullable|string|max:40', 'docs.*.required' => 'nullable|boolean'];
    }

    /** Save the list from the editor; blank rows are dropped, keys stay stable for existing items. */
    public static function save(array $rows): void
    {
        $used = [];
        $list = [];
        foreach ($rows as $r) {
            $label = trim((string) ($r['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $key = Str::of((string) ($r['key'] ?? '') ?: $label)->slug('_')->limit(36, '')->value() ?: 'document';
            $base = $key;
            for ($i = 2; in_array($key, $used, true); $i++) {
                $key = $base.'_'.$i;
            }
            $used[] = $key;
            $list[] = ['key' => $key, 'label' => $label, 'required' => filter_var($r['required'] ?? false, FILTER_VALIDATE_BOOLEAN)];
        }
        Setting::put('application_documents', json_encode($list));
    }
}
