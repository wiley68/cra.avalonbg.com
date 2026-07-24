<?php

test('ops process-manager samples exist for queue and scheduler', function () {
    $root = base_path('ops/samples');

    expect($root . '/README.md')->toBeFile()
        ->and($root . '/supervisor/cra-queue-worker.conf')->toBeFile()
        ->and($root . '/systemd/cra-queue-worker.service')->toBeFile()
        ->and($root . '/systemd/cra-scheduler.service')->toBeFile()
        ->and($root . '/systemd/cra-scheduler.timer')->toBeFile()
        ->and($root . '/docker-compose.workers.yml')->toBeFile();

    $supervisor = file_get_contents($root . '/supervisor/cra-queue-worker.conf');
    $queueService = file_get_contents($root . '/systemd/cra-queue-worker.service');
    $schedulerService = file_get_contents($root . '/systemd/cra-scheduler.service');
    $compose = file_get_contents($root . '/docker-compose.workers.yml');

    expect($supervisor)->toContain('queue:work')
        ->and($queueService)->toContain('queue:work')
        ->and($schedulerService)->toContain('schedule:run')
        ->and($compose)->toContain('queue:work')
        ->and($compose)->toContain('schedule:work');
});
