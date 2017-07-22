<?php

namespace Lamer1\LaravelFriendships\Traits;

use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lamer1\LaravelFriendships\Models\Friendship;

trait Friendshipable
{
    /**
     * @var Friendship
     */
    public $friendshipModel = Friendship::class;

    /**
     * @return HasMany
     */
    public function friendsOfModel()
    {
        return $this->hasMany($this->getFriendshipClassName(), 'sender_id');
    }

    /**
     * @return HasMany
     */
    public function modelIsFriendOf()
    {
        return $this->hasMany($this->getFriendshipClassName(), 'recipient_id');
    }

    /**
     * @param $recipient
     * @param null|int $status
     * @param boolean $wt
     * @param null|int $statusInitiator
     * @return Friendship
     */
    public function getFriendship($recipient, $status = null, $wt = false, $statusInitiator = null)
    {
        $res = $this->getFriendshipQuery(...func_get_args())->first();
        if ($res) {
            $res->setUser($this);
        }
        return $res;
    }

    /**
     * @param $withModel
     * @param null $status
     * @param boolean $wt
     * @param int $statusInitiator
     * @return Builder|Friendship
     */
    public function getFriendshipQuery($withModel, $status = null, $wt = false, $statusInitiator = null)
    {
        $model = $this->getFriendshipClassName();
        $query = $model::between($this, $withModel)->whereStatus($status)->whereStatusInitiator($statusInitiator);
        if ($wt) {
            $query->withTrashed();
        }
        return $query;
    }

    /**
     * @param null|int|array $status
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getFriendships($status = null)
    {
        return $this->getFriendshipsQuery(...func_get_args())->get()->each(function ($item) {
            $item->setUser($this);
        });
    }

    /**
     * @param null|int|array $status
     * @return Builder
     */
    public function getFriendshipsQuery($status = null)
    {
        $model = $this->getFriendshipClassName();
        /** @var Friendship|Builder $query */
        return $model
            ::where(function ($q) {
                $q->whereSender($this)->orWhereRecipient($this);
            })
            ->whereStatus($status);
    }

    /**
     * @param int $status
     * @return Collection
     */
    public function getFriends($status = Friendship::STATUS_ACCEPTED)
    {
        return $this->getFriendsQuery($status)->get();
    }

    /**
     * @param null $status
     * @return Builder
     */
    public function getFriendsQuery($status = null)
    {
        $ids = $this->getFriendshipsQuery($status)->getQuery()->get(['sender_id', 'recipient_id'])->toArray();

        return static::where('id', '<>', $this->getKey())->whereIn('id', array_unique(array_merge(array_column($ids, 'sender_id'), array_column($ids, 'recipient_id'))));
    }

    /**
     * @param null|int $status
     * @return int
     */
    public function getFriendshipsCount($status = null)
    {
        return $this->getFriendshipsQuery($status)->count();
    }

    /**
     * @param Friendshipable|Model $recipient
     * @param int $status
     * @param boolean $wt
     * @param Friendshipable|int $statusInitiator
     * @return mixed
     */
    public function statused($recipient, $status = null, $wt = false, $statusInitiator = null)
    {
        return $this->getFriendshipQuery(...func_get_args())->exists();
    }

    /**
     * @param Model $friend
     * @param int $status
     * @return Friendship|boolean
     */
    public function makeFriendship($friend, $status = Friendship::STATUS_PENDING)
    {
        if ($friendship = $this->getFriendship($friend, null, true)) {
            $attributes = [
                'status' => $status
            ];
            if (!$friendship->validateFriendshipChanging($attributes)) {
                return false;
            }
            if ($friendship->trashed()) {
                $friendship->restore();
            }
            $updateResult = $friendship->update($attributes);
            if ($updateResult) {
                return $friendship;
            }
            return false;
        }
        $friendshipModel = $this->getFriendshipClassName();
        /** @var Friendship $friendship */
        $friendship = new $friendshipModel([
            'status' => $status
        ], $this);

        $friendship->sender()->associate($this);
        $friendship->recipient()->associate($friend);

        $friendship->update();
        return $friendship;
    }

    public function getFriendshipClassName()
    {
        return Friendship::class;
    }
}