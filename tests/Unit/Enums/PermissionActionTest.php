<?php

use SaeidSharafi\LaravelPermissionGenerator\Enums\PermissionAction;

test('enum has expected cases', function () {
    $expectedCases = [
        'VIEW' => 'view',
        'CREATE' => 'create',
        'UPDATE' => 'update',
        'DELETE' => 'delete',
        'VIEW_SCOPED' => 'view_scoped',
        'UPDATE_SCOPED' => 'update_scoped',
        'DELETE_SCOPED' => 'delete_scoped',
        'CONTROL_SCOPED' => 'control_scoped',
        'VIEW_USAGE_SCOPED' => 'view_usage_scoped',
        'VIEW_ANY' => 'view_any',
        'VIEW_OWN' => 'view_own',
        'UPDATE_ANY' => 'update_any',
        'UPDATE_OWN' => 'update_own',
        'DELETE_ANY' => 'delete_any',
        'DELETE_OWN' => 'delete_own',
        'MANAGE' => 'manage',
    ];

    $actualCases = [];
    foreach (PermissionAction::cases() as $case) {
        $actualCases[$case->name] = $case->value;
    }

    expect($actualCases)->toBe($expectedCases);
});

test('all cases have string values', function () {
    foreach (PermissionAction::cases() as $case) {
        expect($case->value)->toBeString();
    }
});

test('scoped actions are correctly named', function () {
    $scopedActions = [
        PermissionAction::VIEW_SCOPED,
        PermissionAction::UPDATE_SCOPED,
        PermissionAction::DELETE_SCOPED,
        PermissionAction::CONTROL_SCOPED,
        PermissionAction::VIEW_USAGE_SCOPED,
    ];

    foreach ($scopedActions as $action) {
        expect($action->value)->toEndWith('_scoped');
    }
});
