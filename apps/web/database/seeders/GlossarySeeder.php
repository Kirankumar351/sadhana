<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Glossary;
use Illuminate\Database\Seeder;

/**
 * THE FIRST THING TO SEED. Ten AI features inject this into their prompts, and the
 * translation reviewer checks compliance against it.
 *
 * This is not a polish item — it is the SEO thesis expressed as data.
 *
 * "Notification" must stay నోటిఫికేషన్ and must NOT become ప్రకటన. Both are valid Telugu;
 * only one is what an aspirant actually types into Google. A semantically correct
 * translation that nobody searches for ranks for nothing, and ranking on Telugu exam
 * queries is where 60% of Year 1 acquisition comes from.
 *
 * The rule column drives behaviour:
 *   transliterate      keep the sound, change the script  (Constable -> కానిస్టేబుల్)
 *   do_not_translate   leave in Latin entirely            (SSC, RRB, IBPS)
 *   translate          render the meaning                 (rare — only where Telugu has a
 *                                                          genuinely established word)
 *   latin_only         numerals and currency stay Latin   (2026, Rs 28,940)
 *
 * Extend this as the content editor finds terms. Every addition should be justified by what
 * people search for, not by what is most elegant.
 */
class GlossarySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->terms() as $term) {
            Glossary::updateOrCreate(
                ['term_en' => $term['term_en']],
                $term,
            );
        }
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function terms(): array
    {
        return [
            // ---- process words: the highest-traffic search terms in the product ----
            ['term_en' => 'Notification', 'term_te' => 'నోటిఫికేషన్', 'rule' => 'transliterate', 'category' => 'process', 'note' => 'NEVER ప్రకటన. This is the single highest-volume Telugu exam query word.'],
            ['term_en' => 'Syllabus', 'term_te' => 'సిలబస్', 'rule' => 'transliterate', 'category' => 'process', 'note' => 'NEVER పాఠ్యాంశాలు'],
            ['term_en' => 'Cutoff', 'term_te' => 'కటాఫ్', 'rule' => 'transliterate', 'category' => 'process'],
            ['term_en' => 'Exam pattern', 'term_te' => 'ఎగ్జామ్ పాటర్న్', 'rule' => 'transliterate', 'category' => 'process'],
            ['term_en' => 'Previous papers', 'term_te' => 'ప్రీవియస్ పేపర్స్', 'rule' => 'transliterate', 'category' => 'process'],
            ['term_en' => 'Answer key', 'term_te' => 'ఆన్సర్ కీ', 'rule' => 'transliterate', 'category' => 'process'],
            ['term_en' => 'Admit card', 'term_te' => 'అడ్మిట్ కార్డ్', 'rule' => 'transliterate', 'category' => 'process'],
            ['term_en' => 'Hall ticket', 'term_te' => 'హాల్ టికెట్', 'rule' => 'transliterate', 'category' => 'process'],
            ['term_en' => 'Result', 'term_te' => 'రిజల్ట్', 'rule' => 'transliterate', 'category' => 'process'],
            ['term_en' => 'Merit list', 'term_te' => 'మెరిట్ లిస్ట్', 'rule' => 'transliterate', 'category' => 'process'],
            ['term_en' => 'Application form', 'term_te' => 'అప్లికేషన్ ఫారం', 'rule' => 'transliterate', 'category' => 'process'],
            ['term_en' => 'Application fee', 'term_te' => 'అప్లికేషన్ ఫీజు', 'rule' => 'transliterate', 'category' => 'process'],
            ['term_en' => 'Last date', 'term_te' => 'చివరి తేదీ', 'rule' => 'translate', 'category' => 'process', 'note' => 'One of the few genuinely translated terms — చివరి తేదీ is what people say and search.'],
            ['term_en' => 'Vacancies', 'term_te' => 'ఖాళీలు', 'rule' => 'translate', 'category' => 'process'],
            ['term_en' => 'Eligibility', 'term_te' => 'అర్హత', 'rule' => 'translate', 'category' => 'process'],
            ['term_en' => 'Age limit', 'term_te' => 'వయస్సు పరిమితి', 'rule' => 'translate', 'category' => 'process'],
            ['term_en' => 'Age relaxation', 'term_te' => 'వయస్సు సడలింపు', 'rule' => 'translate', 'category' => 'process'],
            ['term_en' => 'Qualification', 'term_te' => 'విద్యార్హత', 'rule' => 'translate', 'category' => 'process'],
            ['term_en' => 'Reservation', 'term_te' => 'రిజర్వేషన్', 'rule' => 'transliterate', 'category' => 'process'],
            ['term_en' => 'Domicile', 'term_te' => 'స్థానికత', 'rule' => 'translate', 'category' => 'process'],
            ['term_en' => 'Mains', 'term_te' => 'మెయిన్స్', 'rule' => 'transliterate', 'category' => 'process'],
            ['term_en' => 'Prelims', 'term_te' => 'ప్రిలిమ్స్', 'rule' => 'transliterate', 'category' => 'process'],
            ['term_en' => 'Interview', 'term_te' => 'ఇంటర్వ్యూ', 'rule' => 'transliterate', 'category' => 'process'],
            ['term_en' => 'Physical test', 'term_te' => 'ఫిజికల్ టెస్ట్', 'rule' => 'transliterate', 'category' => 'process'],
            ['term_en' => 'Negative marking', 'term_te' => 'నెగెటివ్ మార్కింగ్', 'rule' => 'transliterate', 'category' => 'process'],

            // ---- exam names: conducting bodies stay in Latin, universally ----
            ['term_en' => 'TGPSC', 'term_te' => 'TGPSC', 'rule' => 'do_not_translate', 'category' => 'exam_name'],
            ['term_en' => 'APPSC', 'term_te' => 'APPSC', 'rule' => 'do_not_translate', 'category' => 'exam_name'],
            ['term_en' => 'SSC', 'term_te' => 'SSC', 'rule' => 'do_not_translate', 'category' => 'exam_name'],
            ['term_en' => 'UPSC', 'term_te' => 'UPSC', 'rule' => 'do_not_translate', 'category' => 'exam_name'],
            ['term_en' => 'RRB', 'term_te' => 'RRB', 'rule' => 'do_not_translate', 'category' => 'exam_name'],
            ['term_en' => 'IBPS', 'term_te' => 'IBPS', 'rule' => 'do_not_translate', 'category' => 'exam_name'],
            ['term_en' => 'DSC', 'term_te' => 'DSC', 'rule' => 'do_not_translate', 'category' => 'exam_name'],
            ['term_en' => 'TET', 'term_te' => 'TET', 'rule' => 'do_not_translate', 'category' => 'exam_name'],
            ['term_en' => 'Group 1', 'term_te' => 'గ్రూప్ 1', 'rule' => 'transliterate', 'category' => 'exam_name', 'note' => 'Numeral stays Latin.'],
            ['term_en' => 'Group 2', 'term_te' => 'గ్రూప్ 2', 'rule' => 'transliterate', 'category' => 'exam_name'],
            ['term_en' => 'Group 3', 'term_te' => 'గ్రూప్ 3', 'rule' => 'transliterate', 'category' => 'exam_name'],
            ['term_en' => 'Group 4', 'term_te' => 'గ్రూప్ 4', 'rule' => 'transliterate', 'category' => 'exam_name'],

            // ---- post names ----
            ['term_en' => 'Constable', 'term_te' => 'కానిస్టేబుల్', 'rule' => 'transliterate', 'category' => 'post_name'],
            ['term_en' => 'Sub Inspector', 'term_te' => 'సబ్ ఇన్‌స్పెక్టర్', 'rule' => 'transliterate', 'category' => 'post_name'],
            ['term_en' => 'Naib Tahsildar', 'term_te' => 'నాయబ్ తహసీల్దార్', 'rule' => 'transliterate', 'category' => 'post_name'],
            ['term_en' => 'Tahsildar', 'term_te' => 'తహసీల్దార్', 'rule' => 'transliterate', 'category' => 'post_name'],
            ['term_en' => 'Junior Assistant', 'term_te' => 'జూనియర్ అసిస్టెంట్', 'rule' => 'transliterate', 'category' => 'post_name'],
            ['term_en' => 'Village Revenue Officer', 'term_te' => 'VRO', 'rule' => 'do_not_translate', 'category' => 'post_name'],
            ['term_en' => 'Panchayat Secretary', 'term_te' => 'పంచాయతీ కార్యదర్శి', 'rule' => 'translate', 'category' => 'post_name'],
            ['term_en' => 'Teacher', 'term_te' => 'ఉపాధ్యాయుడు', 'rule' => 'translate', 'category' => 'post_name'],

            // ---- qualification ----
            ['term_en' => 'Degree', 'term_te' => 'డిగ్రీ', 'rule' => 'transliterate', 'category' => 'qualification'],
            ['term_en' => 'Intermediate', 'term_te' => 'ఇంటర్మీడియట్', 'rule' => 'transliterate', 'category' => 'qualification'],
            ['term_en' => 'SSC / 10th', 'term_te' => 'పదో తరగతి', 'rule' => 'translate', 'category' => 'qualification'],
            ['term_en' => 'Diploma', 'term_te' => 'డిప్లొమా', 'rule' => 'transliterate', 'category' => 'qualification'],
            ['term_en' => 'B.Tech', 'term_te' => 'B.Tech', 'rule' => 'do_not_translate', 'category' => 'qualification'],
            ['term_en' => 'Post Graduation', 'term_te' => 'పీజీ', 'rule' => 'transliterate', 'category' => 'qualification'],
            ['term_en' => 'ITI', 'term_te' => 'ITI', 'rule' => 'do_not_translate', 'category' => 'qualification'],

            // ---- categories: always Latin abbreviations, as they appear on every form ----
            ['term_en' => 'General', 'term_te' => 'జనరల్', 'rule' => 'transliterate', 'category' => 'category'],
            ['term_en' => 'OBC', 'term_te' => 'OBC', 'rule' => 'do_not_translate', 'category' => 'category'],
            ['term_en' => 'SC', 'term_te' => 'SC', 'rule' => 'do_not_translate', 'category' => 'category'],
            ['term_en' => 'ST', 'term_te' => 'ST', 'rule' => 'do_not_translate', 'category' => 'category'],
            ['term_en' => 'EWS', 'term_te' => 'EWS', 'rule' => 'do_not_translate', 'category' => 'category'],
            ['term_en' => 'PwD', 'term_te' => 'PwD', 'rule' => 'do_not_translate', 'category' => 'category'],
            ['term_en' => 'Ex-serviceman', 'term_te' => 'మాజీ సైనికుడు', 'rule' => 'translate', 'category' => 'category'],

            // ---- subjects ----
            ['term_en' => 'Indian Polity', 'term_te' => 'భారత రాజ్యాంగం', 'rule' => 'translate', 'category' => 'subject'],
            ['term_en' => 'Current Affairs', 'term_te' => 'కరెంట్ అఫైర్స్', 'rule' => 'transliterate', 'category' => 'subject'],
            ['term_en' => 'General Studies', 'term_te' => 'జనరల్ స్టడీస్', 'rule' => 'transliterate', 'category' => 'subject'],
            ['term_en' => 'Telangana Movement', 'term_te' => 'తెలంగాణ ఉద్యమం', 'rule' => 'translate', 'category' => 'subject'],
            ['term_en' => 'Indian Economy', 'term_te' => 'భారత ఆర్థిక వ్యవస్థ', 'rule' => 'translate', 'category' => 'subject'],
            ['term_en' => 'Geography', 'term_te' => 'భూగోళశాస్త్రం', 'rule' => 'translate', 'category' => 'subject'],
            ['term_en' => 'History', 'term_te' => 'చరిత్ర', 'rule' => 'translate', 'category' => 'subject'],
            ['term_en' => 'Reasoning', 'term_te' => 'రీజనింగ్', 'rule' => 'transliterate', 'category' => 'subject'],
            ['term_en' => 'Quantitative Aptitude', 'term_te' => 'అర్థమెటిక్', 'rule' => 'transliterate', 'category' => 'subject'],

            // ---- product words ----
            ['term_en' => 'Daily quiz', 'term_te' => 'రోజు ప్రశ్న', 'rule' => 'translate', 'category' => 'product'],
            ['term_en' => 'Streak', 'term_te' => 'స్ట్రీక్', 'rule' => 'transliterate', 'category' => 'product'],
            ['term_en' => 'Doubt', 'term_te' => 'సందేహం', 'rule' => 'translate', 'category' => 'product'],
            ['term_en' => 'Mock test', 'term_te' => 'మాక్ టెస్ట్', 'rule' => 'transliterate', 'category' => 'product'],
            ['term_en' => 'Study material', 'term_te' => 'స్టడీ మెటీరియల్', 'rule' => 'transliterate', 'category' => 'product'],
            ['term_en' => 'Leaderboard', 'term_te' => 'లీడర్‌బోర్డ్', 'rule' => 'transliterate', 'category' => 'product'],

            // ---- formatting rules, read by every prompt ----
            ['term_en' => 'Numerals', 'term_te' => null, 'rule' => 'latin_only', 'category' => 'formatting', 'note' => 'Always Latin: 2026, 16,614, Rs 28,940. Telugu numerals are not read fluently by this audience.'],
            ['term_en' => 'Currency', 'term_te' => null, 'rule' => 'latin_only', 'category' => 'formatting', 'note' => 'Rs or the rupee sign plus Latin digits. Never spell out amounts in Telugu words.'],
            ['term_en' => 'Dates', 'term_te' => null, 'rule' => 'latin_only', 'category' => 'formatting', 'note' => 'Latin digits with a Telugu or English month name. 12 సెప్టెంబర్ 2026.'],
        ];
    }
}
