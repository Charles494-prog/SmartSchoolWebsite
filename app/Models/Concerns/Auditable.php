<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Writes every create / update / delete to audit_logs, with old and new values,
 * so changes (e.g. grades) are never made silently.
 */
trait Auditable
{
    protected static array $auditHidden = ['password', 'remember_token', 'updated_at', 'created_at'];

    public static function bootAuditable(): void
    {
        static::created(fn ($m) => $m->writeAudit('created', [], $m->auditValues($m->getAttributes())));
        static::updated(function ($m) {
            $changes = $m->auditValues($m->getChanges());
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($m->auditValues($m->getOriginal()), $changes);
            $m->writeAudit('updated', $old, $changes);
        });
        static::deleted(fn ($m) => $m->writeAudit('deleted', $m->auditValues($m->getAttributes()), []));
    }

    protected function auditValues(array $values): array
    {
        return array_diff_key($values, array_flip(static::$auditHidden));
    }

    protected function writeAudit(string $action, array $old, array $new): void
    {
        AuditLog::record($action, $this, $old, $new);
    }
}
