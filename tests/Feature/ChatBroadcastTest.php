<?php

use App\Events\ChatMessageSent;
use App\Models\Chat;
use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

beforeEach(function () {
    seedMasterData();
});

it('broadcasts ChatMessageSent on the private chat channel', function () {
    $kontrak = KontrakMagang::factory()->create();

    $chat = Chat::create([
        'kontrak_magang_id' => $kontrak->id,
        'sender_id' => $kontrak->mahasiswa_id,
        'receiver_id' => $kontrak->dosen_id,
        'message' => 'Halo',
    ]);

    $event = new ChatMessageSent($chat);

    expect($event)->toBeInstanceOf(ShouldBroadcast::class);

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-chat.'.$kontrak->id);

    expect($event->broadcastAs())->toBe('ChatMessageSent')
        ->and($event->broadcastWith())
        ->toMatchArray([
            'id' => $chat->id,
            'kontrak_magang_id' => $kontrak->id,
            'sender_id' => $kontrak->mahasiswa_id,
            'receiver_id' => $kontrak->dosen_id,
            'message' => 'Halo',
        ]);
});

it('authorizes the chat channel for the owning mahasiswa via the broadcast auth endpoint', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    $kontrak = KontrakMagang::factory()->create(['mahasiswa_id' => $mahasiswa->id]);

    actingAsMahasiswa($mahasiswa);

    $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-chat.'.$kontrak->id,
        'socket_id' => '1234.5678',
    ])->assertOk();
});

it('forbids the chat channel for an unrelated mahasiswa', function () {
    $owner = Mahasiswa::factory()->create();
    $kontrak = KontrakMagang::factory()->create(['mahasiswa_id' => $owner->id]);

    $intruder = Mahasiswa::factory()->create();
    actingAsMahasiswa($intruder);

    $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-chat.'.$kontrak->id,
        'socket_id' => '1234.5678',
    ])->assertForbidden();
});
