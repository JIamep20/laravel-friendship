<?php

namespace Tests;

use Lamer1\LaravelFriendships\Models\Friendship;
use Lamer1\LaravelFriendships\Traits\Friendshipable;

class FriendshipTest extends TestCase
{
    
    public function test_create_friendship()
    {
        $sender = $this->cu();
        $recipient = $this->cu();

        $friendship = $sender->makeFriendship($recipient);

        $this->assertEquals(true, $sender->statused($recipient, Friendship::PENDING));
        $this->assertEquals(false, $sender->statused($recipient, Friendship::ACCEPTED));
        $this->assertEquals(true, $sender->statused($recipient));

        $this->assertEquals(true, $friendship->exists);
        
        $this->assertEquals($sender->getKey(), $friendship->sender_id);
        $this->assertEquals($recipient->getKey(), $friendship->recipient_id);
        
        $this->assertNotEquals($sender->getKey(), $friendship->recipient_id);
        $this->assertNotEquals($recipient->getKey(), $friendship->sender_id);
    }
    
    public function test_send_friendship_request()
    {
        $sender = $this->cu();
        $sender2 = $this->cu();
        $recipient = $this->cu();
        
        $sfriendship1 = $sender->makeFriendship($recipient, Friendship::DENIED);
        $sfriendship2 = $sender2->makeFriendship($recipient, Friendship::ACCEPTED);
        
        $this->assertCount(1, $recipient->getFriendships(Friendship::DENIED));
        $this->assertCount(1, $recipient->getFriendships(Friendship::ACCEPTED));
        $this->assertCount(2, $recipient->getFriendships());
        
        $this->assertEquals($sender->getKey(), $sfriendship1->status_initiator);
        $this->assertEquals($sender2->getKey(), $sfriendship2->status_initiator);
    }

    public function test_manipulating_friendship()
    {
        $sender = $this->cu();
        $recipient = $this->cu();

        $friendship = $sender->makeFriendship($recipient);

        $tmp = $recipient->updateFriendship($friendship, [
            'status' => Friendship::ACCEPTED
        ]);
        
        $this->assertEquals($friendship, $tmp);
        $this->assertEquals(true, $tmp->status == $friendship->status && $tmp->status == Friendship::ACCEPTED);

        $recipient->updateFriendship($friendship, [
            'status' => Friendship::BLOCKED
        ]);
        $this->assertEquals(Friendship::BLOCKED, $friendship->status);
        $this->assertEquals($recipient->getKey(), $friendship->status_initiator);
        
        $tmp = $sender->makeFriendship($recipient);
        $this->assertEquals(false, $tmp);
        
        $tmp = $sender->updateFriendship($friendship);
        $this->assertEquals(false, $tmp);
        
        $tmp = $recipient->updateFriendship($friendship, [
            'status' => Friendship::ACCEPTED
        ]);
        $this->assertEquals(true, !!$tmp);

        $tmp = $sender->updateFriendship($friendship, [
            'status' => Friendship::DENIED
        ]);
        $this->assertEquals(true, !!$tmp);
        
        $tmp = $recipient->updateFriendship($friendship, [
            'status' => Friendship::PENDING
        ]);
        $this->assertEquals(true, !!$tmp);
    }

    public function test_delete_and_restore_friendship()
    {
        $sender = $this->cu();
        $recipient = $this->cu();

        $friendship = $sender->makeFriendship($recipient);

        $sender->deleteFriendship($friendship);

        $this->assertEquals(true, $friendship->trashed());
        $this->assertEquals($sender->getKey(), $friendship->status_initiator);

        $tmp = $recipient->makeFriendship($sender);
        $this->assertEquals(false, $tmp);
        $tmp = $recipient->updateFriendship($friendship);
        $this->assertEquals(false, $tmp);

        $friendship = $sender->makeFriendship($recipient, Friendship::ACCEPTED);

        $this->assertEquals(false, $friendship->trashed());
        $this->assertEquals(Friendship::ACCEPTED, $friendship->status);

        $sender->deleteFriendship($friendship);

        $tmp = $recipient->makeFriendship($sender);
        $this->assertEquals(false, $tmp);
    }

    public function test_friends_count()
    {
        $count = 5;
        $recipient = $this->cu();

        $senders = $this->cu($count);
        $senders->each(function ($item) use ($recipient) {
            /** @var Friendshipable $item */
            $item->makeFriendship($recipient);
        });
        $this->assertEquals(1, $senders->random()->getFriendshipsCount());
        $this->assertEquals($count, $recipient->getFriendshipsCount());
    }
}
