<?php

namespace Lamer1\LaravelFriendships\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Lamer1\LaravelFriendships\Traits\Friendshipable;
use Illuminate\Foundation\Auth\User as Authenticatable;

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
    
    private $user = null;

    const STATUS_PENDING = 1;
    const STATUS_ACCEPTED = 2;
    const STATUS_DENIED = 3;
    const STATUS_BLOCKED = 4;

    const ALL_STATUSES = 0;


    /**
     * How user can change friendship if he is not last status initiator
     */
    public static $statusChangeOpponentRules = [
        self::STATUS_PENDING => self::ALL_STATUSES,
        self::STATUS_ACCEPTED => self::ALL_STATUSES,
        self::STATUS_DENIED => [self::STATUS_PENDING, self::STATUS_BLOCKED],
        self::STATUS_BLOCKED => [],
    ];

    /**
     * How user can change friendship if he is last status initiator
     */
    public static $statusChangeSelfRules = [
        self::STATUS_PENDING => [self::STATUS_BLOCKED],
        self::STATUS_ACCEPTED => self::ALL_STATUSES,
        self::STATUS_DENIED => self::ALL_STATUSES,
        self::STATUS_BLOCKED => self::ALL_STATUSES,
    ];

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

    /**
     * @param Friendshipable|Authenticatable $user
     * @return $this
     */
    public function setUser($user = null)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return \Illuminate\Contracts\Auth\Authenticatable|Authenticatable|Friendshipable|null
     */
    public function getUser()
    {
        if ($this->user) {
            return $this->user;
        }
        return Auth::user();
    }

    /**
     * @param array $options
     * @return bool
     */
    public function save(array $options = [])
    {
        if ($this->validateFriendshipChanging()) {
            return parent::save($options);
        }
        return false;
    }

    /**
     * @param array $attributes
     * @param array $options
     * @return bool
     */
    public function update(array $attributes = [], array $options = [])
    {
        return $this->updateFriendship(...func_get_args());
    }

    /**
     * @param array $attributes
     * @param array $options
     * @return bool
     */
    public function updateFriendship(array $attributes = [], array $options = [])
    {
        if (!array_key_exists('status_initiator', $attributes)) {
            $attributes['status_initiator'] = $this->getUser()->getKey();
        }

        return $this->fill($attributes)->save($options);
    }

    public function __construct(array $attributes = [], $user = null)
    {
        $this->setUser($user);
        return parent::__construct($attributes);
    }

    /**
     * @param array $attributesToCheck
     * @return bool
     */
    public function validateFriendshipChanging(array $attributesToCheck = [])
    {
        $changedAttributes = $attributesToCheck ? $attributesToCheck : $this->getDirty();
        foreach ($changedAttributes as $key => $value) {
            $methodName = 'validate' . ucfirst(camel_case($key));
            if (method_exists($this, $methodName) && !call_user_func_array([$this, $methodName], [$key, $value])) {
                return false;
            }
        }
        return true;
    }

    protected function validateStatusInitiator()
    {
        if (!in_array($this->getUser()->getKey(), [$this->sender_id, $this->recipient_id])) {
            return false;
        }
        return true;
    }

    protected function validateStatus()
    {
        $oldStatus = $this->getOriginal('status');

        if (!$oldStatus) {
            return true;
        }

        $oldStatusInitiator = $this->getOriginal('status_initiator');

        $rulesSet =
            $this->getUser()->getKey() == $oldStatusInitiator ?
                $this->statusChangeRules($oldStatus, 'statusChangeSelfRules') :
                $this->statusChangeRules($oldStatus, 'statusChangeOpponentRules');
        if ($rulesSet !== self::ALL_STATUSES && !in_array($this->status, $rulesSet)) {
            return false;
        }
        return true;
    }

    /**
     * @param null|int $index
     * @param string $rulesSet
     * @return array|null
     */
    private function statusChangeRules($index = null, $rulesSet = 'statusChangeOpponentRules')
    {
        $rules = self::${$rulesSet};
        if (is_null($index)) {
            return $rules;
        }
        if (array_key_exists($index, $rules)) {
            return $rules[$index];
        }
        return null;
    }
}
