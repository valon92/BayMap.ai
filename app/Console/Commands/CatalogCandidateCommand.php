<?php

namespace App\Console\Commands;

use App\Models\Catalog\PlatformCandidate;
use App\Services\Catalog\PlatformCuratorService;
use Illuminate\Console\Command;

class CatalogCandidateCommand extends Command
{
    protected $signature = 'catalog:candidate
        {action : register|analyze|approve|reject|list}
        {--country= : ISO2 country code}
        {--name= : Platform display name}
        {--url= : Base URL}
        {--category= : Category slug}
        {--id= : Candidate ID}
        {--reason= : Rejection reason}
        {--pending : Filter pending candidates (list)}';

    protected $description = 'Curate marketplace candidates — register, analyze, approve before live routing';

    public function handle(PlatformCuratorService $curator): int
    {
        return match ($this->argument('action')) {
            'register' => $this->register($curator),
            'analyze' => $this->analyze($curator),
            'approve' => $this->approve($curator),
            'reject' => $this->reject($curator),
            'list' => $this->listCandidates(),
            default => $this->invalidAction(),
        };
    }

    private function register(PlatformCuratorService $curator): int
    {
        $country = strtoupper((string) $this->option('country'));
        $name = (string) $this->option('name');
        $url = (string) $this->option('url');

        if ($country === '' || $name === '' || $url === '') {
            $this->error('Required: --country= --name= --url=');

            return self::FAILURE;
        }

        $candidate = $curator->registerCandidate(
            $country,
            $name,
            $url,
            'manual',
            $this->option('category') ? (string) $this->option('category') : null,
        );

        $this->info("Candidate #{$candidate->id} registered: {$candidate->name} ({$candidate->domain})");
        $this->line('Status: discovered — run catalog:candidate analyze --id='.$candidate->id);

        if (config('catalog.curation.auto_analyze_on_register', false)) {
            $curator->analyze($candidate->id);
            $this->info('Auto-analysis completed.');
        }

        return self::SUCCESS;
    }

    private function analyze(PlatformCuratorService $curator): int
    {
        $id = (int) $this->option('id');
        if ($id <= 0) {
            $this->error('Required: --id=');

            return self::FAILURE;
        }

        $analysis = $curator->analyze($id);
        $this->info("Candidate #{$id} analyzed.");
        $this->line('Reachable: '.(($analysis['reachable'] ?? false) ? 'yes' : 'no'));
        $this->line('Adapter signals: '.implode(', ', $analysis['adapter_signals'] ?? []));
        $this->line('Category signals: '.implode(', ', $analysis['category_signals'] ?? []));
        if (($analysis['warnings'] ?? []) !== []) {
            $this->warn('Warnings: '.implode(' | ', $analysis['warnings']));
        }

        return self::SUCCESS;
    }

    private function approve(PlatformCuratorService $curator): int
    {
        $id = (int) $this->option('id');
        if ($id <= 0) {
            $this->error('Required: --id=');

            return self::FAILURE;
        }

        $platform = $curator->approve($id, $this->option('reason') ? (string) $this->option('reason') : null);
        $this->info("Approved → live platform slug: {$platform->slug}");

        return self::SUCCESS;
    }

    private function reject(PlatformCuratorService $curator): int
    {
        $id = (int) $this->option('id');
        $reason = (string) ($this->option('reason') ?: 'Rejected during manual review');

        if ($id <= 0) {
            $this->error('Required: --id=');

            return self::FAILURE;
        }

        $curator->reject($id, $reason);
        $this->info("Candidate #{$id} rejected.");

        return self::SUCCESS;
    }

    private function listCandidates(): int
    {
        $query = PlatformCandidate::query()->with('country:id,iso2,name');

        if ($this->option('country')) {
            $iso = strtoupper((string) $this->option('country'));
            $query->whereHas('country', fn ($q) => $q->where('iso2', $iso));
        }

        if ($this->option('pending')) {
            $query->whereIn('status', [
                PlatformCandidate::STATUS_DISCOVERED,
                PlatformCandidate::STATUS_ANALYZING,
                PlatformCandidate::STATUS_NEEDS_REVIEW,
            ]);
        }

        $rows = $query->orderByDesc('updated_at')->limit(50)->get();

        $this->table(
            ['ID', 'Country', 'Name', 'Domain', 'Status', 'Adapter', 'Trust'],
            $rows->map(fn (PlatformCandidate $c) => [
                $c->id,
                $c->country?->iso2,
                $c->name,
                $c->domain,
                $c->status,
                $c->adapter_guess ?? '-',
                $c->trust_estimate ?? '-',
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function invalidAction(): int
    {
        $this->error('Action must be: register, analyze, approve, reject, list');

        return self::FAILURE;
    }
}
