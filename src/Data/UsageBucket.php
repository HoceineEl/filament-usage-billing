<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Data;

use Illuminate\Database\Eloquent\Model;

/**
 * One slice of a module's usage for a period, attributed to whatever the
 * consuming application wants to break the bill down by.
 *
 * The label is stored alongside the morph because invoices freeze it: the
 * attributed record may be renamed or deleted long before the facture stops
 * mattering.
 */
final readonly class UsageBucket
{
    public function __construct(
        public int $quantity,
        public ?string $attributionType = null,
        public ?int $attributionId = null,
        public ?string $attributionLabel = null,
    ) {}

    public static function unattributed(int $quantity): self
    {
        return new self(quantity: $quantity);
    }

    public static function for(Model $attribution, int $quantity, ?string $label = null): self
    {
        return new self(
            quantity: $quantity,
            attributionType: $attribution->getMorphClass(),
            attributionId: (int) $attribution->getKey(),
            attributionLabel: $label ?? self::guessLabel($attribution),
        );
    }

    /**
     * The stable key that makes a counter row unique. Unattributed usage folds
     * into the empty string rather than null, because a nullable column cannot
     * carry uniqueness in MySQL.
     */
    public function key(): string
    {
        if ($this->attributionType === null || $this->attributionId === null) {
            return '';
        }

        return "{$this->attributionType}:{$this->attributionId}";
    }

    public function isAttributed(): bool
    {
        return $this->key() !== '';
    }

    /**
     * @return array{quantity: int, attribution_type: ?string, attribution_id: ?int, attribution_label: ?string}
     */
    public function toArray(): array
    {
        return [
            'quantity' => $this->quantity,
            'attribution_type' => $this->attributionType,
            'attribution_id' => $this->attributionId,
            'attribution_label' => $this->attributionLabel,
        ];
    }

    private static function guessLabel(Model $attribution): ?string
    {
        foreach (['name', 'title', 'label'] as $attribute) {
            $value = $attribution->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
