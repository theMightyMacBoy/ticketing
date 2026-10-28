<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $ticket_id
 * @property int|null $user_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime
 * @property int|null $message_id
 * @property bool $is_inline
 */
class Attachment extends Model
{
    use UsesConfiguredTable;

    protected $fillable = [
        'ticket_id',
        'message_id',
        'user_id',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'is_inline',
    ];

    protected $casts = [
        'size' => 'integer',
        'is_inline' => 'boolean',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.ticket'));
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.message'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    protected function configuredTable(): string
    {
        return 'attachments';
    }
}
