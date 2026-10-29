<?php

/**
 * The presets are written into `roles.description`, a `varchar(255)` column.
 *
 * A longer description is silently cut by the database, so the administrator
 * would sign in to a role whose purpose stops mid-sentence. The check keeps the
 * config and the column honest with each other.
 */
it('keeps every role description inside the roles table column', function () {
    $roles = config('crypto_call_center.roles');

    expect($roles)->toBeArray()->not->toBeEmpty();

    foreach ($roles as $key => $role) {
        if (! is_array($role) || ! isset($role['description'])) {
            continue;
        }

        expect(mb_strlen($role['description']))
            ->toBeLessThanOrEqual(255, "The `{$key}` role description does not fit `roles.description`.");
    }
});
