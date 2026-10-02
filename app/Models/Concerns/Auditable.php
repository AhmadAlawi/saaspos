<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

/**
 * Writes a row to `audit_logs` on create/update/delete, only while an
 * authenticated user is driving the request — console/import/seeder
 * writes are deliberately not logged, since there's no "who did this"
 * to record and bulk imports would otherwise flood the table.
 *
 * `updated` skips no-op saves (e.g. a form resubmit that changes
 * nothing but `updated_at`) and never records the fields listed in
 * `AUDIT_HIDDEN` (passwords, PINs, tokens).
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->writeAuditLog('created', null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            unset($changes['updated_at']);
            if (empty($changes)) {
                return;
            }
            $old = array_intersect_key($model->getOriginal(), $changes);
            $model->writeAuditLog('updated', $old, $changes);
        });

        static::deleted(function ($model) {
            $model->writeAuditLog('deleted', $model->getAttributes(), null);
        });
    }

    protected function writeAuditLog(string $event, ?array $old, ?array $new): void
    {
        if (! Auth::check()) {
            return;
        }

        $hidden = ['password', 'pin', 'remember_token', 'mfa_secret', 'mfa_recovery_codes'];
        if ($old) {
            $old = array_diff_key($old, array_flip($hidden));
        }
        if ($new) {
            $new = array_diff_key($new, array_flip($hidden));
        }

        AuditLog::create([
            'user_id'        => Auth::id(),
            'auditable_type' => static::class,
            'auditable_id'   => $this->getKey(),
            'event'          => $event,
            'old_values'     => $old,
            'new_values'     => $new,
            'url'            => request()?->fullUrl(),
            'ip_address'     => request()?->ip(),
            'created_at'     => now(),
        ]);
    }
}
