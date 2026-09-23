<?php

use App\Events\ChatMessageSent;
use App\Models\Chat;
use App\Models\DosenPembimbing;
use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    seedMasterData();
});

it('broadcasts ChatMessageSent on the private chat channel', function () {
    $kontrak = KontrakMagang::factory()->create();

    $chat = Chat::create([
        'kontrak_magang_id' => $kontrak->id,
        'sender_id' => $kontrak->mahasiswa_id,
        'sender_type' => Chat::SENDER_MAHASISWA,
        'receiver_id' => $kontrak->dosen_id,
        'receiver_type' => Chat::SENDER_DOSEN,
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
            'sender_type' => Chat::SENDER_MAHASISWA,
            'receiver_id' => $kontrak->dosen_id,
            'receiver_type' => Chat::SENDER_DOSEN,
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

it('authorizes the chat channel for the assigned dosen (non-default guard)', function () {
    // Regression: the default guard is `mahasiswa`. Before the channel declared
    // its `guards`, /broadcasting/auth resolved the subscriber from the default
    // guard only, so a logged-in dosen was treated as a guest and got a 403 —
    // the dosen never subscribed and chat never updated live for them.
    $mahasiswa = mahasiswaDenganPreferensi();
    $dosen = DosenPembimbing::factory()->create();
    $kontrak = KontrakMagang::factory()->create([
        'mahasiswa_id' => $mahasiswa->id,
        'dosen_id' => $dosen->id,
    ]);

    Auth::guard('dosen')->login($dosen);

    $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-chat.'.$kontrak->id,
        'socket_id' => '1234.5678',
    ])->assertOk();
});

it('forbids the chat channel for a dosen who does not supervise the contract', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    $owner = DosenPembimbing::factory()->create();
    $kontrak = KontrakMagang::factory()->create([
        'mahasiswa_id' => $mahasiswa->id,
        'dosen_id' => $owner->id,
    ]);

    $other = DosenPembimbing::factory()->create();
    Auth::guard('dosen')->login($other);

    $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-chat.'.$kontrak->id,
        'socket_id' => '1234.5678',
    ])->assertForbidden();
});
