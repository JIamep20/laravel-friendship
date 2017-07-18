<?php

namespace Tests;

use Lamer1\LaravelFriendships\Models\Friendship;
use Lamer1\LaravelFriendships\Traits\Friendshipable;
use Tests\Models\TestUser;

class FriendshipTest extends TestCase
{
    
    public function test_create_friendship()
    {
        $sender = $this->cu();
        $recipient = $this->cu();

        $friendship = $sender->makeFriendship($recipient);
        
        $this->assertInstanceOf(TestUser::class, $sender);
        $this->assertEquals($sender, $friendship->getUser());
        
        $this->assertEquals(true, $sender->statused($recipient, Friendship::STATUS_PENDING));
        $this->assertEquals(false, $sender->statused($recipient, Friendship::STATUS_ACCEPTED));
        $this->assertEquals(true, $sender->statused($recipient));
        
        $this->assertEquals(true, $friendship->exists);
        
        $this->assertEquals($sender->getKey(), $friendship->getAttribute('sender_id'));
        $this->assertEquals($recipient->getKey(), $friendship->getAttribute('recipient_id'));
        
        $this->assertNotEquals($sender->getKey(), $friendship->getAttribute('recipient_id'));
        $this->assertNotEquals($recipient->getKey(), $friendship->getAttribute('sender_id'));
    }
    
    public function test_send_friendship_request()
    {
        $sender = $this->cu();
        $sender2 = $this->cu();
        $recipient = $this->cu();
        
        $sfriendship1 = $sender->makeFriendship($recipient, Friendship::STATUS_DENIED);
        $sfriendship2 = $sender2->makeFriendship($recipient, Friendship::STATUS_ACCEPTED);
        
        $this->assertCount(1, $recipient->getFriendships(Friendship::STATUS_DENIED));
        $this->assertCount(1, $recipient->getFriendships(Friendship::STATUS_ACCEPTED));
        $this->assertCount(2, $recipient->getFriendships());
        
        $this->assertEquals($sender->getKey(), $sfriendship1->getAttribute('status_initiator'));
        $this->assertEquals($sender2->getKey(), $sfriendship2->getAttribute('status_initiator'));
    }

    public function test_change_status()
    {
        $sender = $this->cu();
        $recipient = $this->cu();
        
        $sender->makeFriendship($recipient, Friendship::STATUS_ACCEPTED);
        
        $friendship = $recipient->getFriendship($sender, Friendship::STATUS_ACCEPTED);

        $this->assertEquals(Friendship::STATUS_ACCEPTED, $friendship->getAttribute('status'));
        $this->assertEquals($sender->getKey(), $friendship->getAttribute('status_initiator'));

        $this->assertEquals(true, $friendship->update(['status' => Friendship::STATUS_DENIED]));
        $friendship = $recipient->getFriendship($sender);

        $this->assertEquals(Friendship::STATUS_DENIED, $friendship->getAttribute('status'));
        $this->assertEquals($recipient->getKey(), $friendship->getAttribute('status_initiator'));
    }

    public function test_friends_count()
    {
        $sender = $this->cu();

        $this->cu(10)->each(function ($item) use ($sender) {
            /** @var Friendshipable $item */
            $sender->makeFriendship($item, Friendship::STATUS_ACCEPTED);
            $this->assertEquals(1, $item->getFriendshipsCount());
            $this->assertEquals(1, $item->getFriendshipsCount(Friendship::STATUS_ACCEPTED));
            $this->assertEquals(0, $item->getFriendshipsCount(Friendship::STATUS_DENIED));
        });

        $this->assertEquals(10, $sender->getFriendshipsCount());
        $this->assertEquals(0, $sender->getFriendshipsCount(Friendship::STATUS_DENIED));
        $this->assertEquals(10, $sender->getFriendshipsCount(Friendship::STATUS_ACCEPTED));
    }
    
    public function test_user_can_not_accept_his_request()
    {
        $sender = $this->cu();
        $recipient = $this->cu();
        
        $friendship = $sender->makeFriendship($recipient);
        
        $this->assertEquals(true, $friendship->update(['status' => Friendship::STATUS_PENDING]));
        $this->assertEquals(false, $friendship->update(['status' => Friendship::STATUS_ACCEPTED]));
        $this->assertEquals(false, $friendship->update(['status' => Friendship::STATUS_DENIED]));
        $this->assertEquals(true, $friendship->update(['status' => Friendship::STATUS_BLOCKED]));
    }
    
    public function test_user_can_send_new_request_if_denied()
    {
        $sender = $this->cu();
        $recipient = $this->cu();
        
        $sender->makeFriendship($recipient);
        
        $friendship = $recipient->getFriendship($sender);
        $this->assertEquals(true, $friendship->update(['status' => Friendship::STATUS_DENIED]));
        
        $friendship = $sender->getFriendship($recipient);
        $this->assertEquals(Friendship::STATUS_DENIED, $friendship->getAttribute('status'));
        
        $this->assertEquals(true, $friendship->update(['status' => Friendship::STATUS_PENDING]));
        $this->assertEquals(false, $friendship->update(['status' => Friendship::STATUS_ACCEPTED]));
    }
}
