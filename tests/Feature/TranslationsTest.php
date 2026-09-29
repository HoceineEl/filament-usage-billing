<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

it('ships the same translation keys in every language', function (string $locale): void {
    $keys = fn (string $locale): array => array_keys(Arr::dot(require __DIR__."/../../resources/lang/{$locale}/billing.php"));

    expect($keys($locale))->toEqualCanonicalizing($keys('en'));
})->with(['fr', 'ar']);
