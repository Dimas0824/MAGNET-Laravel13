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
        'sender_id',
        'sender_type',
        'receiver_id',
        'receiver_type',
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
     * Sender identity. Role comes from sender_type, never from raw id
     * comparison, because mahasiswa.id and dosen.id share an id space and can
     * collide (both = 1).
     */
    public function getSenderAttribute()
    {
        $kontrak = $this->kontrakMagang;

        if (! $kontrak) {
            return null;
        }

        return $this->sender_type === self::SENDER_MAHASISWA
            ? $kontrak->mahasiswa
            : $kontrak->dosenPembimbing;
    }

    /**
     * Receiver identity, resolved the same way as the sender.
     */
    public function getReceiverAttribute()
    {
        $kontrak = $this->kontrakMagang;

        if (! $kontrak) {
            return null;
        }

        return $this->receiver_type === self::SENDER_MAHASISWA
            ? $kontrak->mahasiswa
            : $kontrak->dosenPembimbing;
    }

    /**
     * Scope untuk pesan antara dua user dalam kontrak tertentu.
     * Matches on the participant role so id collisions cannot leak messages
     * between a mahasiswa and an unrelated dosen with the same id.
     */
    public function scopeBetweenUsers($query, $user1Id, $user2Id, $kontrakMagangId)
    {
        return $query->where('kontrak_magang_id', $kontrakMagangId)
            ->where(function ($q) {
                // A message is between the pair as long as its sender and
                // receiver are the two distinct roles for this kontrak.
                $q->whereIn('sender_type', [self::SENDER_MAHASISWA, self::SENDER_DOSEN])
                    ->whereIn('receiver_type', [self::SENDER_MAHASISWA, self::SENDER_DOSEN])
                    ->whereColumn('sender_type', '!=', 'receiver_type');
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
        return $this->sender_type === self::SENDER_MAHASISWA;
    }

    /**
     * Check if message is sent by dosen
     */
    public function isSentByDosen(): bool
    {
        return $this->sender_type === self::SENDER_DOSEN;
    }

    /**
     * Whether the given viewer (identified by role) is the author.
     *
     * @param  string  $role  'mahasiswa' | 'dosen'
     */
    public function isMineFor(string $role): bool
    {
        return $this->sender_type === $role;
    }
}
