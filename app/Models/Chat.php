<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chat extends Model
{
    use HasFactory;

    public const SENDER_MAHASISWA = 'mahasiswa';

    public const SENDER_DOSEN = 'dosen';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'kontrak_magang_id',
        'sender_user_id',
        'receiver_user_id',
        'message',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relasi ke KontrakMagang
     */
    public function kontrakMagang(): BelongsTo
    {
        return $this->belongsTo(KontrakMagang::class, 'kontrak_magang_id');
    }

    /**
     * The sender identity (registry-backed). Falls back to the kontrak+role
     * resolution when a legacy row has no `sender_user_id` yet (expand phase).
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /**
     * The receiver identity (registry-backed), same legacy fallback.
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_user_id');
    }

    /**
     * Resolve the registry User for a participant, falling back to the kontrak
     * + role when the FK column is still null on a legacy row.
     */
    public function resolveParticipant(string $which): ?User
    {
        $relation = $which === 'receiver' ? 'receiver' : 'sender';
        $typeColumn = $which === 'receiver' ? 'receiver_type' : 'sender_type';

        $user = $this->{$relation};

        if ($user !== null) {
            return $user;
        }

        $kontrak = $this->kontrakMagang;

        if (! $kontrak) {
            return null;
        }

        $party = $this->{$typeColumn} === self::SENDER_MAHASISWA
            ? $kontrak->mahasiswa
            : $kontrak->dosenPembimbing;

        return $party?->user;
    }

    /**
     * The sender's role-table row for this conversation (mahasiswa or dosen),
     * resolved through the kontrak + the registry FK.
     */
    public function senderPartyAttribute()
    {
        return $this->partyFor('sender');
    }

    /**
     * The receiver's role-table row for this conversation.
     */
    public function receiverPartyAttribute()
    {
        return $this->partyFor('receiver');
    }

    /**
     * Resolve the mahasiswa/dosen row a participant maps to, using the registry
     * FK first (canonical) and falling back to the kontrak when resolving.
     */
    protected function partyFor(string $which): ?Model
    {
        $kontrak = $this->kontrakMagang;

        if (! $kontrak) {
            return null;
        }

        return $this->roleFor($which) === self::SENDER_MAHASISWA
            ? $kontrak->mahasiswa
            : $kontrak->dosenPembimbing;
    }

    /**
     * The role ('mahasiswa'|'dosen') of a participant, derived from the registry
     * FK compared against the kontrak's mahasiswa/dosen user_ids — no reliance
     * on the dropped polymorphic type column.
     */
    public function roleFor(string $which = 'sender'): ?string
    {
        $column = $which === 'receiver' ? 'receiver_user_id' : 'sender_user_id';
        $userId = $this->{$column};

        if ($userId === null) {
            return null;
        }

        $kontrak = $this->kontrakMagang;

        if (! $kontrak) {
            return null;
        }

        if ((int) $kontrak->mahasiswa?->user_id === (int) $userId) {
            return self::SENDER_MAHASISWA;
        }

        if ((int) $kontrak->dosenPembimbing?->user_id === (int) $userId) {
            return self::SENDER_DOSEN;
        }

        return null;
    }

    /**
     * Scope untuk pesan antara dua user dalam kontrak tertentu.
     * Matches on the registry participants for this kontrak.
     */
    public function scopeBetweenUsers($query, $user1Id, $user2Id, $kontrakMagangId)
    {
        return $query->where('kontrak_magang_id', $kontrakMagangId)
            ->where(function ($q) use ($user1Id, $user2Id) {
                $q->where(function ($inner) use ($user1Id, $user2Id) {
                    $inner->where('sender_user_id', $user1Id)->where('receiver_user_id', $user2Id);
                })->orWhere(function ($inner) use ($user1Id, $user2Id) {
                    $inner->where('sender_user_id', $user2Id)->where('receiver_user_id', $user1Id);
                });
            });
    }

    /**
     * Scope untuk pesan berdasarkan kontrak magang
     */
    public function scopeByKontrak($query, $kontrakMagangId)
    {
        return $query->where('kontrak_magang_id', $kontrakMagangId);
    }

    /**
     * Check if message is sent by mahasiswa
     */
    public function isSentByMahasiswa(): bool
    {
        return $this->roleFor('sender') === self::SENDER_MAHASISWA;
    }

    /**
     * Check if message is sent by dosen
     */
    public function isSentByDosen(): bool
    {
        return $this->roleFor('sender') === self::SENDER_DOSEN;
    }

    /**
     * Whether the given viewer (identified by role) is the author.
     *
     * @param  string  $role  'mahasiswa' | 'dosen'
     */
    public function isMineFor(string $role): bool
    {
        return $this->roleFor('sender') === $role;
    }
}
