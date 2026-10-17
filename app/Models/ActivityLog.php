<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    // Keys that must never land in the audit trail.
    private const REDACT = ['password', 'password_confirmation', 'current_password', 'remember_token', 'token', '_token'];

    protected function casts(): array
    {
        return ['properties' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?Model $subject = null, array $properties = [], ?int $userId = null): self
    {
        return self::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'properties' => self::redact($properties) ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }

    private static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, self::REDACT, true)) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = self::redact($value);
            }
        }

        return $data;
    }

    public function description(): string
    {
        return str($this->action)->replace(['.', '_'], ' ')->ucfirst()->toString();
    }
}
