<?php

namespace App\Jobs;

use App\Models\ProductRepository;
use App\Models\User;
use App\Services\VcsSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncProductRepositoryJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function __construct(
        public int $repositoryId,
        public ?int $triggeredByUserId = null,
    ) {
    }

    public function uniqueId(): string
    {
        return (string) $this->repositoryId;
    }

    public function handle(VcsSyncService $sync): void
    {
        $repository = ProductRepository::query()
            ->with(['connection', 'product'])
            ->findOrFail($this->repositoryId);

        $actor = $this->triggeredByUserId !== null
            ? User::query()->find($this->triggeredByUserId)
            : null;

        $sync->sync($repository, $actor);
    }
}
