<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class AuditTrail
{
    public static function write(
        string $event,
        Model $subject,
        array $details = []
    ): void {
        DB::table('audit_logs')->insert([
            'actor_id' => auth()->id(),
            'event' => $event,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'details' => json_encode(
                $details,
                JSON_THROW_ON_ERROR
            ),
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
