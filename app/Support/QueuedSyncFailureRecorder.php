<?php

namespace App\Support;

use App\Models\ProductIntegrationLink;
use App\Models\ProductRepository;
use Throwable;

class QueuedSyncFailureRecorder
{
    public static function recordIntegrationLink(int $linkId, Throwable $exception): void
    {
        $link = ProductIntegrationLink::query()->find($linkId);

        if ($link === null) {
            return;
        }

        $summary = is_array($link->last_sync_summary) ? $link->last_sync_summary : [];

        $link->forceFill([
            'last_sync_summary' => array_merge($summary, [
                'last_error' => self::safeMessage($exception),
                'queue_failed' => true,
                'soft_fail' => false,
                'failed_at' => now()->toIso8601String(),
            ]),
        ])->save();
    }

    public static function recordProductRepository(int $repositoryId, Throwable $exception): void
    {
        $repository = ProductRepository::query()->find($repositoryId);

        if ($repository === null) {
            return;
        }

        $summary = is_array($repository->last_sync_summary) ? $repository->last_sync_summary : [];

        $repository->forceFill([
            'last_sync_summary' => array_merge($summary, [
                'last_error' => self::safeMessage($exception),
                'queue_failed' => true,
                'soft_fail' => false,
                'failed_at' => now()->toIso8601String(),
            ]),
        ])->save();
    }

    public static function safeMessage(Throwable $exception): string
    {
        $class = class_basename($exception);
        $message = trim($exception->getMessage());
        $message = preg_replace('/\b(sk-|ghp_|glpat-|xox[baprs]-)[A-Za-z0-9_\-]+\b/u', '[redacted]', $message) ?? $message;
        $message = preg_replace('/(?i)(api[_-]?token|password|secret|authorization)\s*[:=]\s*\S+/u', '$1=[redacted]', $message) ?? $message;
        $message = mb_substr($message, 0, 400);

        return $message !== '' ? "{$class}: {$message}" : "{$class}: queued sync failed";
    }
}
