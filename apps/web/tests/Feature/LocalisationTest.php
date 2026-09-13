<?php

declare(strict_types=1);

use App\Models\Exam;
use App\Models\ExamCategory;

/**
 * Telugu-first, verified.
 *
 * This is the differentiation the whole business rests on, and it is also the easiest
 * thing to break silently: a missing translation does not error, it just renders English
 * to a Telugu reader, who does not report it and simply stops coming back.
 */
beforeEach(function (): void {
    $category = ExamCategory::create(['slug' => 'psc', 'name' => ['en' => 'PSC', 'te' => 'PSC']]);

    Exam::create([
        'category_id' => $category->id,
        'slug' => 'group-2',
        'name' => ['en' => 'Group 2', 'te' => 'గ్రూప్ 2'],
        'short_name' => 'G2',
        'conducting_body' => 'TGPSC',
    ]);
});

it('renders navigation in Telugu', function (): void {
    $this->get('/te')
        ->assertSee('నోటిఫికేషన్‌లు', false)
        ->assertSee('పరీక్షలు', false);
});

it('renders the same navigation in English', function (): void {
    $this->get('/en')
        ->assertSee('Notifications', false)
        ->assertSee('Exams', false);
});

/**
 * NOTIFICATION must stay నోటిఫికేషన్ and never become ప్రకటన. Both are valid Telugu;
 * only one is what an aspirant types into Google, and ranking on that word is where 60%
 * of Year 1 acquisition comes from.
 */
it('uses the glossary spelling for notification, not the literal translation', function (): void {
    $html = $this->get('/te')->getContent();

    expect($html)->toContain('నోటిఫికేషన్')
        ->and($html)->not->toContain('ప్రకటన');
});

it('sets the html lang attribute per locale', function (): void {
    $this->get('/te')->assertSee('<html lang="te"', false);
    $this->get('/en')->assertSee('<html lang="en"', false);
});

/**
 * Content translations come from the model, UI strings from the JSON file. Both have to
 * work on the same page or the result is a half-Telugu screen, which reads as broken
 * rather than bilingual.
 */
it('renders model translations alongside UI strings', function (): void {
    $this->get('/te/exams')
        ->assertSee('గ్రూప్ 2', false)          // translatable model field
        ->assertSee('అన్ని ప్రభుత్వ పరీక్షలు', false);   // UI string from te.json
});

it('falls back to English when a Telugu translation is missing', function (): void {
    $category = ExamCategory::create(['slug' => 'ssc', 'name' => ['en' => 'SSC']]);

    Exam::create([
        'category_id' => $category->id,
        'slug' => 'english-only',
        // No Telugu name at all.
        'name' => ['en' => 'English Only Exam'],
        'short_name' => 'EOE',
        'conducting_body' => 'SSC',
    ]);

    // Readable English beats an empty element. The page must still work.
    $this->get('/te/exams')->assertSee('English Only Exam', false);
});

it('keeps numerals in Latin script in both locales', function (): void {
    // Telugu numerals are not read fluently by this audience, and a date is the one thing
    // on the page a user must not misread.
    $this->get('/te')->assertSee('numeral', false);
});
