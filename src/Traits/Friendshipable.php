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
        return $this->hasMany($this->friendshipModel, 'sender_id');
    }

    /**
     * @return HasMany
     */
    public function modelIsFriendOf()
    {
        return $this->hasMany($this->friendshipModel, 'recipient_id');
    }

    /**
     * @param Friendshipable|Model $recipient
     * @param boolean|null $status
     * @param Friendshipable|int $statusInitiator
     * @return mixed
     */
    public function statused($recipient, $status = null, $statusInitiator = null)
    {
        $friendshipModel = $this->friendshipModel;
        return $friendshipModel::between($this, $recipient)->whereStatus($status)->whereStatusInitiator($statusInitiator)->exists();
    }

    /**
     * @param null|int|array $status
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getFriendships($status = null)
    {
        return $this->getFriendshipsQuery($status)->get();
    }

    /**
     * @param null|int|array $status
     * @return Builder
     */
    public function getFriendshipsQuery($status = null)
    {
        $model = $this->friendshipModel;
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
    public function getFriends($status = Friendship::ACCEPTED)
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
     * @param Model $friend
     * @param int $status
     * @return Friendship|boolean
     */
    public function makeFriendship($friend, $status = Friendship::PENDING)
    {
        if ($friendship = $this->getFriendship($friend, null, true)) {
            if ($this->callValidateMethod(__FUNCTION__, [$friendship]) == false) {
                return false;
            }
            if ($friendship->trashed()) {
                $friendship->restore();
            }
            return $this->updateFriendship($friendship, [
                'status' => $status
            ]);
        }
        $friendshipModel = $this->friendshipModel;
        /** @var Friendship $friendship */
        $friendship = new $friendshipModel;

        $friendship->sender()->associate($this);
        $friendship->recipient()->associate($friend);

        return $this->updateFriendship($friendship, [
            'sender_id' => $this->getKey(),
            'recipient_id' => $friend->getKey(),
            'status' => $status
        ]);
    }

    /**
     * @param $recipient
     * @param null $status
     * @param bool $wt
     * @return Friendship
     */
    public function getFriendship($recipient, $status = null, $wt = false)
    {
        $query = $this->getFriendshipQuery($recipient, $status);
        if ($wt) {
            $query->withTrashed();
        }
        return $query->first();
    }

    /**
     * @param $withModel
     * @param null $status
     * @return Builder
     */
    public function getFriendshipQuery($withModel, $status = null)
    {
        $model = $this->friendshipModel;
        return $model::between($this, $withModel)->whereStatus($status);
    }

    /**
     * @param string $methodName
     * @param array $params
     * @return bool
     */
    protected function callValidateMethod($methodName, $params = [])
    {
        $methodName = 'validate' . ucfirst($methodName);
        if (!method_exists($this, $methodName)) {
            return true;
        }
        return call_user_func_array([$this, $methodName], $params);
    }

    /**
     * @param Friendship $friendship
     * @param array|mixed $attributes
     * @param boolean $restoreIfTrashed
     *
     * @return Friendship
     */
    public function updateFriendship($friendship, $attributes = [], $restoreIfTrashed = false)
    {
        if (!$friendship) {
            return false;
        }
        if ($this->callValidateMethod(__FUNCTION__, [$friendship, $attributes]) == false) {
            return false;
        }
        if ($restoreIfTrashed && $friendship->trashed()) {
            $friendship->restore();
        }
        $attributes = is_array($attributes) ? $attributes : [$attributes];
        if (!array_key_exists('status_initiator', $attributes)) {
            $attributes['status_initiator'] = $this->getKey();
        }
        $friendship->fill($attributes)->save();
        return $friendship;
    }

    /**
     * @param Friendship $friendship
     *
     * @return boolean
     */
    public function validateMakeFriendship($friendship)
    {
        if ($friendship->status_initiator == $this->getKey()) {
            return true;
        }
        if ($friendship->isStatus([Friendship::BLOCKED]) || $friendship->trashed()) {
            return false;
        }
        return true;
    }

    /**
     * @param Friendshipable|Model $recipient
     * @return bool|null
     */
    public function deleteFriendshipByRecipient($recipient)
    {
        return $this->deleteFriendship($this->getFriendship($recipient));
    }

    /**
     * @param Friendship $friendship
     * @return bool|null
     * @throws \Exception
     */
    public function deleteFriendship($friendship)
    {
        if (!$friendship) {
            return true;
        }
        if ($this->callValidateMethod(__FUNCTION__, [$friendship]) == false) {
            return false;
        }
        $this->updateFriendship($friendship);
        if ($friendship->delete()) {
            return $friendship;
        }
        return false;
    }

    /**
     * @param $recipient
     * @param $attributes
     * @return bool|Model
     */
    public function updateFriendshipByRecipient($recipient, $attributes = [])
    {
        return $this->updateFriendship($this->getFriendship($recipient), $attributes);
    }

    /**
     * @param Friendship $friendship
     *
     * @return boolean
     */
    public function validateUpdateFriendship($friendship)
    {
        if ($friendship->status_initiator == $this->getKey()) {
            return true;
        }
        if ($friendship->isStatus([Friendship::BLOCKED]) || $friendship->trashed()) {
            return false;
        }
        return true;
    }

    public function getFriendshipsCount($status = null)
    {
        return $this->getFriendshipsQuery($status)->count();
    }
}