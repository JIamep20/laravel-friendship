<?php

namespace Tests;

use App\User;
use Faker\Generator;
use Illuminate\Database\Eloquent\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Collection;
use Lamer1\LaravelFriendships\Traits\Friendshipable;
use Tests\Models\TestUser;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * @var Factory
     */
    protected $factory;

    public function setUp()
    {
        parent::setUp();
        $this->app->make('CreateUsersTable')->up();
        $this->app->make('CreateFriendshipsTable')->up();
        $this->factory = $this->app->make(Factory::class);
        $this->prepareFactory();
    }

    public function tearDown()
    {
        $this->app->make('CreateUsersTable')->down();
        $this->app->make('CreateFriendshipsTable')->down();
        $this->factory = null;
        parent::tearDown();
    }

    protected function prepareFactory()
    {
        $this->factory->define(TestUser::class, function (Generator $faker) {
            static $password;

            return [
                'name' => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'password' => $password ?: $password = bcrypt('secret'),
                'remember_token' => str_random(10),
            ];
        });
    }

    /**
     * @param int|null $count
     * @param array $attributes
     * @return Model|Friendshipable|Collection
     */
    public function cu($count = null, $attributes = [])
    {
        if (is_null($count)) {
            return $this->factory->of(TestUser::class)->create($attributes);
        }
        return $this->factory->of(TestUser::class)->times($count)->create($attributes);
    }
}
