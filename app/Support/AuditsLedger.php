<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait AuditsLedger
{
    protected function audit(string $action, Model $record, array $metadata = []): void
    {
        AuditLog::create([
            'company_id' => auth()->user()->company_id,
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $record->getMorphClass(),
            'entity_id' => $record->getKey(),
            'metadata' => $metadata,
        ]);
    }
}
