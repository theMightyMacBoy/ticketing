<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Coderflex\LaravelTicket\Enums\CustomFieldType;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @property string $key
 * @property string|null $ticket_type
 * @property string $data_type
 * @property array<int, string>|null $options
 */
class CustomFieldDefinition extends Model
{
    use UsesConfiguredTable;

    protected $fillable = [
        'name',
        'key',
        'ticket_type',
        'data_type',
        'options',
        'is_required',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
    ];

    public function assertValue(mixed $value): void
    {
        $valid = match ($this->data_type) {
            CustomFieldType::NUMBER->value => is_int($value) || is_float($value),
            CustomFieldType::BOOLEAN->value => is_bool($value),
            CustomFieldType::DATE->value => is_string($value) && strtotime($value) !== false,
            CustomFieldType::SELECT->value => is_string($value) && in_array($value, $this->options ?? [], true),
            default => is_string($value),
        };

        if (! $valid) {
            throw new InvalidArgumentException("Custom field [{$this->key}] does not accept that value.");
        }
    }

    protected function configuredTable(): string
    {
        return 'custom_field_definitions';
    }
}
