<?php

namespace Coderflex\LaravelTicket\Catalog;

use Coderflex\LaravelTicket\Exceptions\MissingCustomFields;
use Coderflex\LaravelTicket\Models\CustomFieldDefinition;

class CatalogForm
{
    /**
     * Required fields on a catalog item must be present when the ticket opens.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function assertSatisfied(?int $serviceId, array $attributes): void
    {
        if (! $serviceId) {
            return;
        }

        $values = is_array($attributes['custom_attributes'] ?? null)
            ? $attributes['custom_attributes']
            : [];

        $missing = CustomFieldDefinition::query()
            ->where('service_id', $serviceId)
            ->where('is_required', true)
            ->get()
            ->filter(function (CustomFieldDefinition $definition) use ($values) {
                if (! array_key_exists($definition->key, $values)) {
                    return true;
                }

                return $values[$definition->key] === null || $values[$definition->key] === '';
            })
            ->pluck('key')
            ->all();

        if ($missing !== []) {
            throw new MissingCustomFields($missing);
        }
    }
}
