<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Ingestion\GenericListingScraper;
use App\Services\Ingestion\Scrapers\AppscScraper;
use App\Services\Ingestion\Scrapers\IbpsScraper;
use App\Services\Ingestion\Scrapers\RrbScraper;
use App\Services\Ingestion\Scrapers\SscApiScraper;
use App\Services\Ingestion\Scrapers\TgprbScraper;
use App\Services\Ingestion\Scrapers\TgpscScraper;
use Database\Factories\ScrapeSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScrapeSource extends Model
{
    /** @use HasFactory<ScrapeSourceFactory> */
    use HasFactory;

    /**
     * Every parser a source may name, and the only ones ScrapeRunner will resolve.
     *
     * An allowlist rather than "any subclass of BaseScraper": `parser_class` is editable in
     * the admin panel, and the container will happily build any class it is given.
     */
    public const PARSERS = [
        TgpscScraper::class => 'TGPSC — current notifications and their PDFs',
        AppscScraper::class => 'APPSC — recruitment notifications and their PDFs',
        SscApiScraper::class => 'SSC — live examinations API',
        IbpsScraper::class => 'IBPS — registration portal links',
        RrbScraper::class => 'RRB — employment notices and their PDFs',
        TgprbScraper::class => 'TGPRB — recruitment cards',
        GenericListingScraper::class => 'Generic — recruitment links on a careers page',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(ExamNotification::class, 'source_id');
    }

    public function parserLabel(): string
    {
        return self::PARSERS[$this->parser_class] ?? class_basename((string) $this->parser_class).' (not allowed)';
    }
}
