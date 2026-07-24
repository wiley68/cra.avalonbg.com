<?php

test('phase 2_E Could 16 UX debt pack stays empty (no P0 list shipped)', function () {
    $doc = file_get_contents(base_path('documents/Phase2_E_Cross_Phase_Polish.md'));

    expect($doc)->toContain('Could 16 Skipped/empty')
        ->and($doc)->toContain('Skipped / empty')
        ->and($doc)->toContain('не са открити P0 UX дефекти')
        ->and(file_exists(base_path('documents/Phase2_E_UX_Debt_Pack.md')))->toBeFalse();
});
