<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chat extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'kontrak_magang_id',
        'sender_id',
        'receiver_id',
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
     * Get sender (bisa mahasiswa atau dosen)
     * Karena tidak ada sender_type, kita perlu menentukan berdasarkan kontrak
     */
    public function getSenderAttribute()
    {
        if ($this->kontrakMagang) {
            // Jika sender_id sama dengan mahasiswa_id dari kontrak, maka sender adalah mahasiswa
            if ($this->sender_id == $this->kontrakMagang->mahasiswa_id) {
                return $this->kontrakMagang->mahasiswa;
            } else {
                return $this->kontrakMagang->dosenPembimbing;
            }
        }

        return null;
    }

    /**
     * Get receiver (bisa mahasiswa atau dosen)
     */
    public function getReceiverAttribute()
    {
        if ($this->kontrakMagang) {
            // Jika receiver_id sama dengan mahasiswa_id dari kontrak, maka receiver adalah mahasiswa
            if ($this->receiver_id == $this->kontrakMagang->mahasiswa_id) {
                return $this->kontrakMagang->mahasiswa;
            } else {
                return $this->kontrakMagang->dosenPembimbing;
            }
        }

        return null;
    }

    /**
     * Scope untuk pesan antara dua user dalam kontrak tertentu
     */
    public function scopeBetweenUsers($query, $user1Id, $user2Id, $kontrakMagangId)
    {
        return $query->where('kontrak_magang_id', $kontrakMagangId)
            ->where(function ($q) use ($user1Id, $user2Id) {
                $q->where(function ($subQ) use ($user1Id, $user2Id) {
                    $subQ->where('sender_id', $user1Id)
                        ->where('receiver_id', $user2Id);
                })->orWhere(function ($subQ) use ($user1Id, $user2Id) {
                    $subQ->where('sender_id', $user2Id)
                        ->where('receiver_id', $user1Id);
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
        if ($this->kontrakMagang) {
            return $this->sender_id == $this->kontrakMagang->mahasiswa_id;
        }

        return false;
    }

    /**
     * Check if message is sent by dosen
     */
    public function isSentByDosen(): bool
    {
        if ($this->kontrakMagang) {
            return $this->sender_id == $this->kontrakMagang->dosen_id;
        }

        return false;
    }
}
