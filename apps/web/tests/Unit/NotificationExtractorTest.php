<?php

declare(strict_types=1);

use App\Services\AI\Contracts\ModelClient;
use App\Services\AI\StructuredResponse;
use App\Services\Ingestion\NotificationExtractor;

/**
 * The notification extractor.
 *
 * A hallucinated deadline is the worst failure this product can produce: a student reads
 * it, plans around it, and misses the real one. The prompt says "never guess a date", but a
 * prompt is an instruction and these tests cover what happens when it is not followed.
 */
function extractorReturning(array $payload): NotificationExtractor
{
    $client = Mockery::mock(ModelClient::class);
    $client->shouldReceive('extract')->andReturn(new StructuredResponse(
        data: $payload,
        model: 'fake-model',
        inputTokens: 400,
        outputTokens: 300,
    ));

    return new NotificationExtractor($client);
}

$source = 'Applications are invited for 783 posts. Last date to apply: 12/09/2026. '
    .'Age limit 18 to 46 years as on 01/07/2026. Qualification: any degree.';

// ---------------------------------------------------------------- date grounding

/**
 * THE TEST THAT MATTERS MOST.
 *
 * A well-formatted invented date parses perfectly well. The only thing separating it from
 * a real one is whether it was actually written on the page, so that is what is checked.
 */
it('drops a date that appears nowhere in the source text', function () use ($source): void {
    $result = extractorReturning([
        'apply_end_date' => '2027-03-15',   // never appears in the source
    ])->extract($source, 'https://example.gov.in/n.pdf');

    expect($result)->not->toHaveKey('apply_end_date');
});

it('keeps a date that is present in the source text', function () use ($source): void {
    $result = extractorReturning([
        'apply_end_date' => '2026-09-12',
    ])->extract($source, 'https://example.gov.in/n.pdf');

    expect($result['apply_end_date'])->toBe('2026-09-12');
});

/**
 * Grounding compares digits, so a date written as 12/09/2026 in the source and returned as
 * 2026-09-12 still counts as grounded. Otherwise the extractor would discard correct dates
 * purely because the model normalised the format.
 */
it('accepts a grounded date regardless of format', function () use ($source): void {
    $result = extractorReturning([
        'age_reference_date' => '2026-07-01',   // source writes it as 01/07/2026
    ])->extract($source, 'https://example.gov.in/n.pdf');

    expect($result['age_reference_date'])->toBe('2026-07-01');
});

it('drops dates that are not plausible for a recruitment', function (string $date) use ($source): void {
    $result = extractorReturning(['apply_end_date' => $date])
        ->extract($source, 'https://example.gov.in/n.pdf');

    expect($result)->not->toHaveKey('apply_end_date');
})->with(['1970-01-01', '2099-12-31', 'not a date', '']);

// ---------------------------------------------------------------- numeric sanity

it('rejects an age outside any real recruitment range', function () use ($source): void {
    $result = extractorReturning(['min_age' => 2, 'max_age' => 150])
        ->extract($source, 'https://example.gov.in/n.pdf');

    expect($result)->not->toHaveKey('min_age')
        ->and($result)->not->toHaveKey('max_age');
});

/**
 * An inverted range means the model mixed up two numbers. Dropping both is safer than
 * publishing a range that would silently exclude every real candidate.
 */
it('drops both ages when the range is inverted', function () use ($source): void {
    $result = extractorReturning(['min_age' => 46, 'max_age' => 18])
        ->extract($source, 'https://example.gov.in/n.pdf');

    expect($result)->not->toHaveKey('min_age')
        ->and($result)->not->toHaveKey('max_age');
});

it('keeps a plausible age range', function () use ($source): void {
    $result = extractorReturning(['min_age' => 18, 'max_age' => 46])
        ->extract($source, 'https://example.gov.in/n.pdf');

    expect($result['min_age'])->toBe(18)->and($result['max_age'])->toBe(46);
});

// ---------------------------------------------------------------- enum safety

it('rejects a qualification that is not in the allowed set', function () use ($source): void {
    $result = extractorReturning(['min_qualification' => 'graduation or equivalent'])
        ->extract($source, 'https://example.gov.in/n.pdf');

    expect($result)->not->toHaveKey('min_qualification');
});

it('normalises a valid qualification', function () use ($source): void {
    $result = extractorReturning(['min_qualification' => 'DEGREE'])
        ->extract($source, 'https://example.gov.in/n.pdf');

    expect($result['min_qualification'])->toBe('degree');
});

/**
 * An invented state code would silently hide the notification from everyone, because the
 * eligibility engine treats an unmatched state as a failed criterion.
 */
it('discards state codes that are not real', function () use ($source): void {
    $result = extractorReturning(['allowed_states' => ['TS', 'XX', 'ZZ']])
        ->extract($source, 'https://example.gov.in/n.pdf');

    expect($result['allowed_states'])->toBe(['TS']);
});

// ---------------------------------------------------------------- failure handling

/**
 * Extraction failing must not lose the notification. A reviewer can fill in a blank draft
 * far faster than they can find the page again.
 */
it('returns a usable draft when the model call throws', function () use ($source): void {
    $client = Mockery::mock(ModelClient::class);
    $client->shouldReceive('extract')->andThrow(new RuntimeException('provider down'));

    $result = (new NotificationExtractor($client))
        ->extract($source, 'https://example.gov.in/notification.pdf');

    expect($result['official_pdf_url'])->toBe('https://example.gov.in/notification.pdf');
});

it('falls back to the source url as the official pdf when none was extracted', function () use ($source): void {
    $result = extractorReturning([])->extract($source, 'https://example.gov.in/n.pdf');

    expect($result['official_pdf_url'])->toBe('https://example.gov.in/n.pdf');
});
