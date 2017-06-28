<?php

namespace Lamer1\LaravelFriendships\Models;

use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Friendship
 * @package Lamer1\LaravelFriendships\Models
 * 
 * @property int $id
 * @property int $status
 * @property int $sender_id
 * @property int $recipient_id
 * @property int $status_initiator
 *
 * @method static Builder|Friendship between($model1, $model2)
 * @method static Builder|Friendship whereStatus($status)
 * @method static Builder|Friendship whereRecipient($recipient)
 * @method static Builder|Friendship orWhereRecipient($recipient)
 * @method static Builder|Friendship whereSender($sender)
 * @method static Builder|Friendship orWhereSender($sender)
 * @method static Builder|Friendship whereStatusInitiator($initiator)
 */
class Friendship extends Model
{
    use SoftDeletes;
    
    protected $guarded = ['id', 'deleted_at'];
    protected $table = 'friendships';

    const PENDING = 1;
    const ACCEPTED = 2;
    const DENIED = 3;
    const BLOCKED = 4;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * @param Builder $query
     * @param Model|integer|string $sender
     * @return mixed
     */
    public function scopeWhereSender($query, $sender)
    {
        return $query->where('sender_id', is_object($sender) ? $sender->getKey() : $sender);
    }

    /**
     * @param Builder $query
     * @param Model|integer|string $sender
     * @return mixed
     */
    public function scopeOrWhereSender($query, $sender)
    {
        return $query->orWhere('sender_id', is_object($sender) ? $sender->getKey() : $sender);
    }

    /**
     * @param Builder $query
     * @param Model|integer|string $recipient
     * @return mixed
     */
    public function scopeWhereRecipient($query, $recipient)
    {
        return $query->where('recipient_id', is_object($recipient) ? $recipient->getKey() : $recipient);
    }

    /**
     * @param Builder $query
     * @param Model|integer|string $recipient
     * @return mixed
     */
    public function scopeOrWhereRecipient($query, $recipient)
    {
        return $query->orWhere('recipient_id', is_object($recipient) ? $recipient->getKey() : $recipient);
    }

    /**
     * @param Builder $query
     * @param Model $model1
     * @param Model $model2
     * @return Builder
     */
    public function scopeBetween($query, $model1, $model2)
    {
        return $query->where(function ($innerQuery) use ($model1, $model2) {
            $innerQuery->where(function ($q) use ($model1, $model2){
                $q->whereSender($model1)->whereRecipient($model2);
            })->orWhere(function ($q) use ($model1, $model2) {
                $q->whereSender($model2)->whereRecipient($model1);
            });
        });
    }

    /**
     * @param Builder $query
     * @param int|Model $initiator
     * @return Builder
     */
    public function scopeWhereStatusInitiator($query, $initiator)
    {
        if (is_null($initiator)) {
            return $query;
        }
        return $query->where('status_initiator', is_object($initiator) ? $initiator->getKey() : $initiator);
    }

    /**
     * @param int|array $status
     * @param int|string $initiator
     * @return bool
     */
    public function isStatus($status, $initiator = null)
    {
        $status = is_array($status) ? $status : [$status];
        if (is_null($initiator)) {
            return in_array($this->status, $status);
        }
        return in_array($this->status, $status) && $this->status_initiator == $initiator;
    }

    /**
     * @param Builder $query
     * @param int $status
     * @return Builder
     */
    public function scopeWhereStatus($query, $status){
        if (is_null($status)) {
            return $query;
        }
        if (is_array($status)) {
            return $query->whereIn('status', $status);
        }
        return $query->where('status', $status);
    }
}
