<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DailyQuiz;
use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\ExamCutoff;
use App\Models\ExamNotification;
use App\Models\Plan;
use App\Models\Question;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Enough real content to see the product work.
 *
 * Modelled on genuine TGPSC/TSLPRB/SSC notifications so that the screens are exercised
 * with realistic shapes: age relaxation by category, an "as on" reference date that is not
 * the deadline, all-India versus state-restricted, and bilingual questions.
 *
 * Every record here is DEMO data. Nothing in this file may be treated as verified — a real
 * notification only reaches `published` after a human checks it against the official PDF.
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->plans();

        $categories = $this->categories();
        $exams = $this->exams($categories);

        $this->notifications($exams);
        $this->cutoffs($exams);
        $this->quiz($exams);
    }

    private function plans(): void
    {
        foreach (config('commerce.plans') as $slug => $plan) {
            Plan::updateOrCreate(['slug' => $slug], [
                'name' => [
                    'en' => Str::headline($slug),
                    'te' => match ($slug) {
                        'free' => 'ఉచితం',
                        'premium' => 'ప్రీమియం',
                        default => Str::headline($slug),
                    },
                ],
                'entitlements' => $plan['entitlements'],
                'price_paise' => $plan['price_paise'],
                'period' => $plan['period'],
                'ai_credits_per_period' => $plan['ai_credits'],
                'is_active' => true,
                'is_public' => true,
            ]);
        }
    }

    /**
     * @return array<string, ExamCategory>
     */
    private function categories(): array
    {
        $rows = [
            'state-psc' => ['en' => 'State PSC', 'te' => 'రాష్ట్ర పబ్లిక్ సర్వీస్ కమిషన్'],
            'police' => ['en' => 'Police', 'te' => 'పోలీస్'],
            'ssc' => ['en' => 'SSC', 'te' => 'SSC'],
            'railway' => ['en' => 'Railway', 'te' => 'రైల్వే'],
            'teaching' => ['en' => 'Teaching', 'te' => 'ఉపాధ్యాయ'],
        ];

        $out = [];
        $order = 0;

        foreach ($rows as $slug => $name) {
            $out[$slug] = ExamCategory::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'sort_order' => $order++],
            );
        }

        return $out;
    }

    /**
     * @param  array<string, ExamCategory>  $categories
     * @return array<string, Exam>
     */
    private function exams(array $categories): array
    {
        $rows = [
            [
                'slug' => 'tgpsc-group-2',
                'category' => 'state-psc',
                'short_name' => 'Group 2',
                'body' => 'Telangana Public Service Commission',
                'state' => 'TS',
                'has_mains' => true,
                'name' => ['en' => 'TGPSC Group 2 Services', 'te' => 'TGPSC గ్రూప్ 2 సర్వీసెస్'],
                'description' => [
                    'en' => 'Group 2 recruits non-gazetted posts across Telangana government departments, including Municipal Commissioner, Assistant Commercial Tax Officer and Sub Registrar.',
                    'te' => 'తెలంగాణ ప్రభుత్వ శాఖల్లో మున్సిపల్ కమిషనర్, అసిస్టెంట్ కమర్షియల్ ట్యాక్స్ ఆఫీసర్, సబ్ రిజిస్ట్రార్ వంటి నాన్-గెజిటెడ్ పోస్టుల భర్తీకి గ్రూప్ 2 పరీక్ష నిర్వహిస్తారు.',
                ],
                'syllabus' => [
                    'en' => [
                        ['title' => 'Paper I — General Studies', 'topics' => ['Current affairs', 'International relations', 'General science', 'Indian polity']],
                        ['title' => 'Paper II — History, Polity and Society', 'topics' => ['Socio-cultural history of India', 'Indian Constitution', 'Social structure']],
                        ['title' => 'Paper III — Economy and Development', 'topics' => ['Indian economy', 'Telangana economy', 'Development issues']],
                        ['title' => 'Paper IV — Telangana Movement and State Formation', 'topics' => ['The idea of Telangana 1948-1970', 'Mobilisation phase 1971-1990', 'Towards formation 1991-2014']],
                    ],
                    'te' => [
                        ['title' => 'పేపర్ I — జనరల్ స్టడీస్', 'topics' => ['కరెంట్ అఫైర్స్', 'అంతర్జాతీయ సంబంధాలు', 'జనరల్ సైన్స్', 'భారత రాజ్యాంగం']],
                        ['title' => 'పేపర్ II — చరిత్ర, రాజ్యాంగం, సమాజం', 'topics' => ['భారతదేశ సామాజిక సాంస్కృతిక చరిత్ర', 'భారత రాజ్యాంగం', 'సామాజిక నిర్మాణం']],
                        ['title' => 'పేపర్ III — ఆర్థిక వ్యవస్థ, అభివృద్ధి', 'topics' => ['భారత ఆర్థిక వ్యవస్థ', 'తెలంగాణ ఆర్థిక వ్యవస్థ', 'అభివృద్ధి అంశాలు']],
                        ['title' => 'పేపర్ IV — తెలంగాణ ఉద్యమం, రాష్ట్ర ఆవిర్భావం', 'topics' => ['తెలంగాణ ఆలోచన దశ 1948-1970', 'ఉద్యమ దశ 1971-1990', 'రాష్ట్ర ఏర్పాటు 1991-2014']],
                    ],
                ],
                'faq' => [
                    'en' => [
                        ['q' => 'How many papers are there in Group 2?', 'a' => 'Four papers of 150 marks each, 600 marks in total.'],
                        ['q' => 'Is there negative marking?', 'a' => 'There is no negative marking in the Group 2 written examination.'],
                    ],
                    'te' => [
                        ['q' => 'గ్రూప్ 2లో ఎన్ని పేపర్లు ఉంటాయి?', 'a' => 'నాలుగు పేపర్లు, ఒక్కొక్కటి 150 మార్కులు — మొత్తం 600 మార్కులు.'],
                        ['q' => 'నెగెటివ్ మార్కింగ్ ఉందా?', 'a' => 'గ్రూప్ 2 రాత పరీక్షలో నెగెటివ్ మార్కింగ్ లేదు.'],
                    ],
                ],
            ],
            [
                'slug' => 'ts-police-constable',
                'category' => 'police',
                'short_name' => 'TS Constable',
                'body' => 'Telangana State Level Police Recruitment Board',
                'state' => 'TS',
                'name' => ['en' => 'TS Police Constable', 'te' => 'TS పోలీస్ కానిస్టేబుల్'],
                'description' => [
                    'en' => 'Recruitment of Police Constables (Civil and Armed Reserve) across Telangana, with a written examination followed by a physical measurement and efficiency test.',
                    'te' => 'తెలంగాణ వ్యాప్తంగా పోలీస్ కానిస్టేబుల్ (సివిల్ మరియు ఆర్మ్‌డ్ రిజర్వ్) పోస్టుల భర్తీ. రాత పరీక్ష తర్వాత శారీరక కొలతలు మరియు దక్షత పరీక్ష ఉంటుంది.',
                ],
                'syllabus' => [
                    'en' => [['title' => 'Preliminary Written Test', 'topics' => ['Arithmetic', 'Reasoning', 'General Studies', 'Current affairs']]],
                    'te' => [['title' => 'ప్రాథమిక రాత పరీక్ష', 'topics' => ['అర్థమెటిక్', 'రీజనింగ్', 'జనరల్ స్టడీస్', 'కరెంట్ అఫైర్స్']]],
                ],
            ],
            [
                'slug' => 'ssc-cgl',
                'category' => 'ssc',
                'short_name' => 'SSC CGL',
                'body' => 'Staff Selection Commission',
                'state' => null,
                'name' => ['en' => 'SSC Combined Graduate Level', 'te' => 'SSC కంబైన్డ్ గ్రాడ్యుయేట్ లెవెల్'],
                'description' => [
                    'en' => 'All-India examination for Group B and Group C posts in central government ministries and departments.',
                    'te' => 'కేంద్ర ప్రభుత్వ మంత్రిత్వ శాఖలు, విభాగాల్లో గ్రూప్ B, గ్రూప్ C పోస్టుల కోసం అఖిల భారత స్థాయి పరీక్ష.',
                ],
            ],
            [
                'slug' => 'rrb-ntpc',
                'category' => 'railway',
                'short_name' => 'RRB NTPC',
                'body' => 'Railway Recruitment Board',
                'state' => null,
                'name' => ['en' => 'RRB NTPC', 'te' => 'RRB NTPC'],
                'description' => [
                    'en' => 'Non-Technical Popular Categories recruitment across Indian Railways zones.',
                    'te' => 'భారతీయ రైల్వే జోన్లలో నాన్-టెక్నికల్ పాపులర్ కేటగిరీస్ పోస్టుల భర్తీ.',
                ],
            ],
            [
                'slug' => 'ts-dsc',
                'category' => 'teaching',
                'short_name' => 'TS DSC',
                'body' => 'Department of School Education, Telangana',
                'state' => 'TS',
                'name' => ['en' => 'TS DSC Teacher Recruitment', 'te' => 'TS DSC ఉపాధ్యాయ నియామకం'],
                'description' => [
                    'en' => 'Recruitment of School Assistants, Secondary Grade Teachers and Language Pandits in Telangana government schools.',
                    'te' => 'తెలంగాణ ప్రభుత్వ పాఠశాలల్లో స్కూల్ అసిస్టెంట్, సెకండరీ గ్రేడ్ టీచర్, లాంగ్వేజ్ పండిట్ పోస్టుల భర్తీ.',
                ],
            ],
        ];

        $out = [];

        foreach ($rows as $row) {
            $out[$row['slug']] = Exam::updateOrCreate(['slug' => $row['slug']], [
                'category_id' => $categories[$row['category']]->id,
                'name' => $row['name'],
                'short_name' => $row['short_name'],
                'conducting_body' => $row['body'],
                'description' => $row['description'],
                'syllabus' => $row['syllabus'] ?? null,
                'faq' => $row['faq'] ?? null,
                'state' => $row['state'],
                'has_mains' => $row['has_mains'] ?? false,
                'is_active' => true,
                'view_count' => random_int(500, 20000),
            ]);
        }

        return $out;
    }

    /**
     * @param  array<string, Exam>  $exams
     */
    private function notifications(array $exams): void
    {
        $rows = [
            [
                'exam' => 'ts-police-constable',
                'slug' => 'ts-police-constable-2026',
                'title' => ['en' => 'TS Police Constable (Civil & AR) 2026', 'te' => 'TS పోలీస్ కానిస్టేబుల్ (సివిల్ & AR) 2026'],
                'org' => 'Telangana State Level Police Recruitment Board',
                'vacancies' => 16614,
                'qualification' => '12th',
                'min_age' => 18,
                'max_age' => 22,
                // The reference date is NOT the deadline. Getting this wrong moves a
                // boundary candidate across the line, which is the whole reason the
                // eligibility engine reads it separately.
                'ref' => '2026-07-01',
                'end' => 12,
                'states' => ['TS'],
                'salary' => [26800, 84400],
            ],
            [
                'exam' => 'tgpsc-group-2',
                'slug' => 'tgpsc-group-2-services-2026',
                'title' => ['en' => 'TGPSC Group 2 Services 2026', 'te' => 'TGPSC గ్రూప్ 2 సర్వీసెస్ 2026'],
                'org' => 'Telangana Public Service Commission',
                'vacancies' => 783,
                'qualification' => 'degree',
                'min_age' => 18,
                'max_age' => 46,
                'ref' => '2026-07-01',
                'end' => 34,
                'states' => ['TS'],
                'salary' => [42300, 132900],
            ],
            [
                'exam' => 'ssc-cgl',
                'slug' => 'ssc-cgl-2026-tier-1',
                'title' => ['en' => 'SSC Combined Graduate Level 2026 — Tier 1', 'te' => 'SSC కంబైన్డ్ గ్రాడ్యుయేట్ లెవెల్ 2026 — టైర్ 1'],
                'org' => 'Staff Selection Commission',
                'vacancies' => 17727,
                'qualification' => 'degree',
                'min_age' => 18,
                'max_age' => 32,
                'ref' => '2026-08-01',
                'end' => 24,
                'states' => null,
                'salary' => [25500, 81100],
            ],
            [
                'exam' => 'rrb-ntpc',
                'slug' => 'rrb-ntpc-2026-graduate',
                'title' => ['en' => 'RRB NTPC 2026 — Graduate Level', 'te' => 'RRB NTPC 2026 — గ్రాడ్యుయేట్ స్థాయి'],
                'org' => 'Railway Recruitment Board',
                'vacancies' => 8113,
                'qualification' => 'degree',
                'min_age' => 18,
                'max_age' => 33,
                'ref' => '2026-07-01',
                'end' => 3,
                'states' => null,
                'salary' => [19900, 63200],
            ],
            [
                'exam' => 'ts-dsc',
                'slug' => 'ts-dsc-2026',
                'title' => ['en' => 'TS DSC 2026 — School Assistant', 'te' => 'TS DSC 2026 — స్కూల్ అసిస్టెంట్'],
                'org' => 'Department of School Education, Telangana',
                'vacancies' => 11062,
                'qualification' => 'degree',
                'min_age' => 18,
                'max_age' => 44,
                'ref' => '2026-07-01',
                'end' => 18,
                'states' => ['TS'],
                'salary' => [37100, 91450],
            ],
        ];

        foreach ($rows as $row) {
            ExamNotification::updateOrCreate(['slug' => $row['slug']], [
                'exam_id' => $exams[$row['exam']]->id,
                'title' => $row['title'],
                'description' => [
                    'en' => 'Applications are invited for '.number_format($row['vacancies']).' posts. Read the official notification before applying.',
                    'te' => number_format($row['vacancies']).' పోస్టుల భర్తీకి దరఖాస్తులు ఆహ్వానిస్తున్నారు. దరఖాస్తు చేసే ముందు అధికారిక నోటిఫికేషన్ చదవండి.',
                ],
                'organisation' => $row['org'],
                'job_type' => 'government',
                'total_vacancies' => $row['vacancies'],
                'min_qualification' => $row['qualification'],
                'min_age' => $row['min_age'],
                'max_age' => $row['max_age'],
                // Standard Telangana relaxations. Real values always come from the PDF.
                'age_relaxation' => ['obc' => 3, 'sc' => 5, 'st' => 5, 'pwd' => 10, 'ex_serviceman' => 3],
                'age_reference_date' => $row['ref'],
                'allowed_states' => $row['states'],
                'apply_start_date' => today()->subDays(10),
                'apply_end_date' => today()->addDays($row['end']),
                'salary_min' => $row['salary'][0],
                'salary_max' => $row['salary'][1],
                'application_fee' => ['general' => 500, 'obc' => 500, 'sc' => 250, 'st' => 250],
                'official_pdf_url' => 'https://example.gov.in/notification.pdf',
                'apply_url' => 'https://example.gov.in/apply',
                'status' => 'published',
                'published_at' => now()->subDays(random_int(0, 6)),
                'verified_at' => now()->subDays(random_int(0, 3)),
            ]);
        }
    }

    /**
     * @param  array<string, Exam>  $exams
     */
    private function cutoffs(array $exams): void
    {
        $trend = [
            2021 => ['general' => 268.0, 'obc' => 255.5, 'sc' => 214.0, 'st' => 201.5],
            2022 => ['general' => 281.5, 'obc' => 268.0, 'sc' => 236.5, 'st' => 219.0],
            2023 => ['general' => 297.0, 'obc' => 284.5, 'sc' => 268.0, 'st' => 241.5],
            2024 => ['general' => 312.5, 'obc' => 299.0, 'sc' => 296.5, 'st' => 268.0],
            2025 => ['general' => 344.0, 'obc' => 331.5, 'sc' => 331.5, 'st' => 301.0],
        ];

        foreach ($trend as $year => $byCategory) {
            foreach ($byCategory as $category => $marks) {
                ExamCutoff::updateOrCreate([
                    'exam_id' => $exams['tgpsc-group-2']->id,
                    'year' => $year,
                    'category' => $category,
                ], [
                    'cutoff_marks' => $marks,
                    'total_marks' => 600,
                    'source_url' => 'https://example.gov.in/results',
                ]);
            }
        }
    }

    /**
     * @param  array<string, Exam>  $exams
     */
    private function quiz(array $exams): void
    {
        $bank = [
            [
                'subject' => 'Indian Polity',
                'ca' => false,
                'q' => [
                    'en' => 'Which Article of the Indian Constitution did Dr. B. R. Ambedkar describe as its "heart and soul"?',
                    'te' => 'డాక్టర్ బి.ఆర్. అంబేద్కర్ భారత రాజ్యాంగంలోని ఏ అధికరణను "రాజ్యాంగ హృదయం మరియు ఆత్మ" అని అభివర్ణించారు?',
                ],
                'o' => [
                    'en' => ['Article 14', 'Article 32', 'Article 19', 'Article 21'],
                    'te' => ['అధికరణ 14', 'అధికరణ 32', 'అధికరణ 19', 'అధికరణ 21'],
                ],
                'a' => 1,
                'x' => [
                    'en' => 'Article 32 provides the Right to Constitutional Remedies, allowing a citizen to approach the Supreme Court directly when a fundamental right is violated.',
                    'te' => 'అధికరణ 32 రాజ్యాంగ పరిహార హక్కును కల్పిస్తుంది. ప్రాథమిక హక్కు ఉల్లంఘనకు గురైనప్పుడు నేరుగా సుప్రీంకోర్టును ఆశ్రయించవచ్చు.',
                ],
            ],
            [
                'subject' => 'Telangana Movement',
                'ca' => false,
                'q' => [
                    'en' => 'On which date was the Gentlemen\'s Agreement signed?',
                    'te' => 'జెంటిల్‌మెన్స్ అగ్రిమెంట్ ఏ తేదీన సంతకం చేయబడింది?',
                ],
                'o' => [
                    'en' => ['20 February 1956', '1 November 1956', '17 September 1948', '2 June 2014'],
                    'te' => ['20 ఫిబ్రవరి 1956', '1 నవంబర్ 1956', '17 సెప్టెంబర్ 1948', '2 జూన్ 2014'],
                ],
                'a' => 0,
                'x' => [
                    'en' => 'Signed on 20 February 1956, it provided for a Regional Council, reservation of posts for Telangana candidates, and retention of surplus Telangana revenues within the region.',
                    'te' => '20 ఫిబ్రవరి 1956న సంతకం చేశారు. రీజనల్ కౌన్సిల్, ఉద్యోగాల్లో రిజర్వేషన్, మిగులు ఆదాయం తెలంగాణలోనే ఖర్చు — ఈ మూడు హామీలు ఇందులో ఉన్నాయి.',
                ],
            ],
            [
                'subject' => 'Indian Economy',
                'ca' => true,
                'q' => [
                    'en' => 'Which body decides the repo rate in India?',
                    'te' => 'భారతదేశంలో రెపో రేటును ఏ సంస్థ నిర్ణయిస్తుంది?',
                ],
                'o' => [
                    'en' => ['Ministry of Finance', 'Monetary Policy Committee of the RBI', 'NITI Aayog', 'SEBI'],
                    'te' => ['ఆర్థిక మంత్రిత్వ శాఖ', 'RBI ద్రవ్య విధాన కమిటీ', 'నీతి ఆయోగ్', 'SEBI'],
                ],
                'a' => 1,
                'x' => [
                    'en' => 'The six-member Monetary Policy Committee of the Reserve Bank of India sets the repo rate and normally meets six times a year.',
                    'te' => 'రిజర్వ్ బ్యాంక్ ఆఫ్ ఇండియా ద్రవ్య విధాన కమిటీ (ఆరుగురు సభ్యులు) రెపో రేటును నిర్ణయిస్తుంది. సాధారణంగా సంవత్సరానికి ఆరుసార్లు సమావేశమవుతుంది.',
                ],
            ],
            [
                'subject' => 'Geography',
                'ca' => false,
                'q' => [
                    'en' => 'On which river is the Sriram Sagar Project built?',
                    'te' => 'శ్రీరాంసాగర్ ప్రాజెక్టు ఏ నదిపై నిర్మించారు?',
                ],
                'o' => [
                    'en' => ['Krishna', 'Godavari', 'Tungabhadra', 'Manjeera'],
                    'te' => ['కృష్ణా', 'గోదావరి', 'తుంగభద్ర', 'మంజీరా'],
                ],
                'a' => 1,
                'x' => [
                    'en' => 'Sriram Sagar Project is on the Godavari in Nizamabad district and irrigates roughly nine lakh acres.',
                    'te' => 'శ్రీరాంసాగర్ ప్రాజెక్టు నిజామాబాద్ జిల్లాలో గోదావరి నదిపై ఉంది. దాదాపు తొమ్మిది లక్షల ఎకరాలకు సాగునీరు అందిస్తుంది.',
                ],
            ],
            [
                'subject' => 'Indian Polity',
                'ca' => false,
                'q' => [
                    'en' => 'Which amendment added the Right to Education as a fundamental right?',
                    'te' => 'విద్యా హక్కును ప్రాథమిక హక్కుగా ఏ సవరణ ద్వారా చేర్చారు?',
                ],
                'o' => [
                    'en' => ['73rd Amendment', '86th Amendment', '42nd Amendment', '101st Amendment'],
                    'te' => ['73వ సవరణ', '86వ సవరణ', '42వ సవరణ', '101వ సవరణ'],
                ],
                'a' => 1,
                'x' => [
                    'en' => 'The 86th Amendment, passed in 2002, inserted Article 21A. It came into force in 2010 — note that the year passed and the year enforced differ, which is a common exam trap.',
                    'te' => '2002లో ఆమోదించిన 86వ సవరణ అధికరణ 21Aను చేర్చింది. ఇది 2010లో అమల్లోకి వచ్చింది. ఆమోదించిన సంవత్సరం, అమల్లోకి వచ్చిన సంవత్సరం వేరు — పరీక్షల్లో ఇది తరచూ అడుగుతారు.',
                ],
            ],
            [
                'subject' => 'Current Affairs',
                'ca' => true,
                'q' => [
                    'en' => 'What is the minimum age to apply for TS Police Constable?',
                    'te' => 'TS పోలీస్ కానిస్టేబుల్ దరఖాస్తుకు కనీస వయస్సు ఎంత?',
                ],
                'o' => ['en' => ['16 years', '18 years', '21 years', '25 years'], 'te' => ['16 సంవత్సరాలు', '18 సంవత్సరాలు', '21 సంవత్సరాలు', '25 సంవత్సరాలు']],
                'a' => 1,
                'x' => [
                    'en' => 'The minimum age is 18 as on the reference date stated in the notification, which is usually 1 July of the recruitment year.',
                    'te' => 'నోటిఫికేషన్‌లో పేర్కొన్న తేదీ నాటికి కనీస వయస్సు 18 సంవత్సరాలు. సాధారణంగా ఇది ఆ సంవత్సరం జూలై 1.',
                ],
            ],
            [
                'subject' => 'General Studies',
                'ca' => false,
                'q' => ['en' => 'Telangana was formed as the 29th state of India in which year?', 'te' => 'తెలంగాణ భారతదేశపు 29వ రాష్ట్రంగా ఏ సంవత్సరంలో ఏర్పడింది?'],
                'o' => ['en' => ['2012', '2013', '2014', '2015'], 'te' => ['2012', '2013', '2014', '2015']],
                'a' => 2,
                'x' => ['en' => 'Telangana was formed on 2 June 2014.', 'te' => 'తెలంగాణ 2014 జూన్ 2న ఏర్పడింది.'],
            ],
            [
                'subject' => 'Reasoning',
                'ca' => false,
                'q' => ['en' => 'If A is the brother of B, and B is the mother of C, how is A related to C?', 'te' => 'A అనేవాడు Bకి సోదరుడు, B అనేది Cకి తల్లి అయితే, Aకి Cతో ఉన్న సంబంధం ఏమిటి?'],
                'o' => ['en' => ['Father', 'Maternal uncle', 'Brother', 'Grandfather'], 'te' => ['తండ్రి', 'మేనమామ', 'సోదరుడు', 'తాత']],
                'a' => 1,
                'x' => ['en' => 'A is the brother of C\'s mother, which makes him C\'s maternal uncle.', 'te' => 'A అనేవాడు C తల్లికి సోదరుడు, కాబట్టి అతను Cకి మేనమామ అవుతాడు.'],
            ],
            [
                'subject' => 'Indian Economy',
                'ca' => false,
                'q' => ['en' => 'What does GST stand for?', 'te' => 'GST అంటే ఏమిటి?'],
                'o' => ['en' => ['General Sales Tax', 'Goods and Services Tax', 'Gross State Tax', 'Government Service Tax'], 'te' => ['జనరల్ సేల్స్ ట్యాక్స్', 'గూడ్స్ అండ్ సర్వీసెస్ ట్యాక్స్', 'గ్రాస్ స్టేట్ ట్యాక్స్', 'గవర్నమెంట్ సర్వీస్ ట్యాక్స్']],
                'a' => 1,
                'x' => ['en' => 'Goods and Services Tax, introduced on 1 July 2017 by the 101st Constitutional Amendment.', 'te' => 'గూడ్స్ అండ్ సర్వీసెస్ ట్యాక్స్. 101వ రాజ్యాంగ సవరణ ద్వారా 2017 జూలై 1న అమల్లోకి వచ్చింది.'],
            ],
            [
                'subject' => 'Telangana Movement',
                'ca' => false,
                'q' => ['en' => 'In which year did the Telangana Praja Samithi form?', 'te' => 'తెలంగాణ ప్రజా సమితి ఏ సంవత్సరంలో ఏర్పడింది?'],
                'o' => ['en' => ['1956', '1969', '1985', '2001'], 'te' => ['1956', '1969', '1985', '2001']],
                'a' => 1,
                'x' => ['en' => 'It formed in 1969 during the first major phase of the Telangana agitation.', 'te' => 'తెలంగాణ ఉద్యమ మొదటి ప్రధాన దశలో 1969లో ఏర్పడింది.'],
            ],
        ];

        $ids = [];

        foreach ($bank as $row) {
            $question = Question::updateOrCreate(
                ['question->en' => $row['q']['en']],
                [
                    'exam_id' => $exams['tgpsc-group-2']->id,
                    'subject' => $row['subject'],
                    'difficulty' => 'medium',
                    'question' => $row['q'],
                    'options' => $row['o'],
                    'correct_index' => $row['a'],
                    'explanation' => $row['x'],
                    'is_current_affairs' => $row['ca'],
                    'origin' => 'human',
                    // Demo data is pre-approved so the quiz renders. In production a key
                    // is only approved after a person checks it against a source.
                    'approved_at' => now(),
                ],
            );

            $ids[] = $question->id;
        }

        DailyQuiz::updateOrCreate(
            ['quiz_date' => today()],
            ['question_ids' => $ids, 'published_at' => now()],
        );
    }
}
