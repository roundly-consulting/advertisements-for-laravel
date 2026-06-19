<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Native, dependency-free translatable attributes.
 *
 * A using model declares `public array $translatable = ['name', ...]` and casts
 * each listed column to `array`. Stored values are per-locale JSON maps
 * (`['en' => '…', 'de' => '…']`). Reading a translatable attribute returns the
 * value for the active locale, falling back to the configured fallback locale,
 * then the first stored value, then null. Writing a bare string stores it under
 * the current locale, so existing single-locale call sites keep working.
 *
 * @phpstan-require-extends Model
 *
 * @property list<string> $translatable
 */
trait HasTranslations
{
    /**
     * Resolve a translatable attribute for the active (or given) locale.
     */
    public function getTranslation(string $attribute, ?string $locale = null): ?string
    {
        $translations = $this->getTranslations($attribute);

        $locale ??= $this->currentLocale();
        $fallback = $this->fallbackLocale();

        if (array_key_exists($locale, $translations) && $translations[$locale] !== null) {
            return $translations[$locale];
        }

        if ($fallback !== null && array_key_exists($fallback, $translations) && $translations[$fallback] !== null) {
            return $translations[$fallback];
        }

        foreach ($translations as $value) {
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Set a single locale's value for a translatable attribute.
     */
    public function setTranslation(string $attribute, string $locale, ?string $value): static
    {
        $translations = $this->getTranslations($attribute);
        $translations[$locale] = $value;

        $this->setAttribute($attribute, $translations);

        return $this;
    }

    /**
     * The full locale => value map for a translatable attribute.
     *
     * @return array<string, string|null>
     */
    public function getTranslations(string $attribute): array
    {
        $value = $this->getAttributeFromArray($attribute);

        if (is_array($value)) {
            /** @var array<string, string|null> $value */
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                /** @var array<string, string|null> $decoded */
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Replace the whole locale => value map for a translatable attribute.
     *
     * @param  array<string, string|null>  $values
     */
    public function setTranslations(string $attribute, array $values): static
    {
        $this->setAttribute($attribute, $values);

        return $this;
    }

    /**
     * Remove a single locale from a translatable attribute.
     */
    public function forgetTranslation(string $attribute, string $locale): static
    {
        $translations = $this->getTranslations($attribute);
        unset($translations[$locale]);

        $this->setAttribute($attribute, $translations);

        return $this;
    }

    /**
     * Whether a translatable attribute has a value for the active (or given) locale.
     */
    public function hasTranslation(string $attribute, ?string $locale = null): bool
    {
        $locale ??= $this->currentLocale();
        $translations = $this->getTranslations($attribute);

        return array_key_exists($locale, $translations) && $translations[$locale] !== null;
    }

    /**
     * Resolve a translatable attribute to its active-locale value on read.
     */
    public function getAttributeValue($key): mixed
    {
        if ($this->isTranslatableAttribute($key)) {
            return $this->getTranslation($key);
        }

        return parent::getAttributeValue($key);
    }

    /**
     * Storing a bare string under the active locale keeps single-locale call
     * sites (and factories) working; a map is stored as-is.
     */
    public function setAttribute($key, $value)
    {
        if ($this->isTranslatableAttribute($key) && is_string($value)) {
            return $this->setTranslation($key, $this->currentLocale(), $value);
        }

        return parent::setAttribute($key, $value);
    }

    protected function isTranslatableAttribute(string $key): bool
    {
        return in_array($key, $this->translatableAttributes(), true);
    }

    /**
     * @return list<string>
     */
    protected function translatableAttributes(): array
    {
        return $this->translatable;
    }

    protected function currentLocale(): string
    {
        return app()->getLocale();
    }

    protected function fallbackLocale(): ?string
    {
        /** @var string|null $fallback */
        $fallback = config('advertisements.fallback_locale');

        return $fallback;
    }
}
