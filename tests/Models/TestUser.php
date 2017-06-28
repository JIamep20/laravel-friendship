<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 25.06.2017
 * Time: 19:21
 */

namespace Tests\Models;


use App\User;
use Lamer1\LaravelFriendships\Traits\Friendshipable;

class TestUser extends User
{
    use Friendshipable;

    protected $table = 'users';
}