<?php

use Illuminate\Support\Facades\Schema;

/**
 * P2-T8 CONTRACT: after every chat row is registry-linked and the model
 * resolves participants through `*_user_id`, drop the polymorphic columns
 * (sender_id/sender_type/receiver_id/receiver_type).
 */
it('drops the polymorphic participant columns from chats', function () {
    foreach (['sender_id', 'sender_type', 'receiver_id', 'receiver_type'] as $col) {
        expect(Schema::hasColumn('chats', $col))->toBeFalse("chats.{$col} should be dropped");
    }
});

it('keeps the registry FK columns on chats', function () {
    expect(Schema::hasColumn('chats', 'sender_user_id'))->toBeTrue()
        ->and(Schema::hasColumn('chats', 'receiver_user_id'))->toBeTrue();
});

it('has a reversible down() that restores the polymorphic columns', function () {
    $migration = glob(database_path('migrations/*_drop_polymorphic_from_chats.php'));

    expect($migration)->not->toBeEmpty();

    $source = file_get_contents($migration[0]);

    expect($source)->toContain('function down')
        ->and($source)->toContain('sender_type')
        ->and($source)->toContain('receiver_type');
});
