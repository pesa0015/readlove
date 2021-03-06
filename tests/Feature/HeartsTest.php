<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Book;
use App\Bookshelf;
use App\User;
use App\Constants\HeartStatus;

class HeartsTest extends TestCase
{
    private function updateHeart($payload)
    {
        $me = $this->newUser(true);

        $token = $me->token;

        $user = factory(User::class)->create();

        $heart = factory(\App\Heart::class)->create([
            'user_id'       => $user->id,
            'heart_user_id' => $me->user->id,
        ]);

        return $this->callHttpWithToken('PUT', 'hearts/' . $user->id, $token, $payload);
    }

    /**
     * @group storeHeart
     *
     */
    public function testStoreHeart()
    {
        $me = $this->newUser(true);

        $token = $me->token;

        $book = factory(Book::class)->create();

        // Test post with missing payload
        $response = $this->callHttpWithToken('POST', 'hearts', $token);
        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'The given data was invalid.',
            'errors' => [
                'userId' => ['The user id field is required.'],
                'bookId' => ['The book id field is required.']
            ]
        ]);

        $payload = [
            'bookId' => $book->id
        ];

        // Test post with missing userId payload
        $response = $this->callHttpWithToken('POST', 'hearts', $token, $payload);
        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'The given data was invalid.',
            'errors' => [
                'userId' => ['The user id field is required.']
            ]
        ]);

        $user = factory(User::class)->create();

        $payload = [
            'bookId' => $book->id,
            'userId' => $user->id
        ];

        $response = $this->callHttpWithToken('POST', 'hearts', $token, $payload);
        $response->assertStatus(403);

        $this->assertDatabaseMissing('bookshelves', [
            'user_id' => $me->user->id,
            'book_id' => $book->id
        ]);

        $this->assertEquals($response->getData(), 'user_have_not_liked_book');

        $bookshelf = factory(Bookshelf::class)->create();

        $this->assertDatabaseHas('bookshelves', [
            'user_id' => $me->user->id,
            'book_id' => $book->id
        ]);

        $this->assertDatabaseMissing('bookshelves', [
            'user_id' => $user->id,
            'book_id' => $book->id
        ]);

        $response = $this->callHttpWithToken('POST', 'hearts', $token, $payload);
        $response->assertStatus(403);
        
        $this->assertEquals($response->getData(), 'partner_have_not_liked_book');

        $user->books()->attach($book);

        $this->assertDatabaseHas('bookshelves', [
            'user_id' => $user->id,
            'book_id' => $book->id
        ]);

        $this->assertDatabaseMissing('hearts', [
            'user_id'       => $me->user->id,
            'heart_user_id' => $user->id,
            'book_id'       => $book->id
        ]);

        $response = $this->callHttpWithToken('POST', 'hearts', $token, $payload);
        $response->assertStatus(200);
        
        $this->assertDatabaseHas('hearts', [
            'user_id'       => $me->user->id,
            'heart_user_id' => $user->id,
            'book_id'       => $book->id
        ]);
    }

    /**
     * @group updateHeart
     *
     */
    public function testUpdateHeartWithStatusApproved()
    {
        $payload = [
            'status' => HeartStatus::APPROVED
        ];

        $response = $this->updateHeart($payload);

        $heart = \App\Heart::findOrFail($response->getData()->id);

        $response->assertStatus(200);
        $response->assertJson([
            'status'        => $payload['status'],
            'haveRead'      => true,
        ])->assertJsonStructure([
            'id',
            'status',
            'haveRead',
            'user' => ['id', 'name', 'slug'],
            'book' => ['id', 'title', 'slug'],
        ]);

        $this->assertDatabaseHas('hearts', [
            'user_id'       => $heart->user_id,
            'heart_user_id' => $heart->heart_user_id,
            'status'        => $payload['status'],
            'have_read'     => true,
        ]);
    }

    /**
     * @group updateHeart
     *
     */
    public function testUpdateHeartWithStatusDenied()
    {
        $payload = [
            'status' => HeartStatus::DENIED
        ];

        $response = $this->updateHeart($payload);

        $heart = \App\Heart::findOrFail($response->getData()->id);

        $response->assertStatus(200);
        $response->assertJson([
            'status'        => $payload['status'],
            'haveRead'      => true,
        ])->assertJsonStructure([
            'id',
            'status',
            'haveRead',
            'user' => ['id', 'name', 'slug'],
            'book' => ['id', 'title', 'slug'],
        ]);

        $this->assertDatabaseHas('hearts', [
            'user_id'       => $heart->user_id,
            'heart_user_id' => $heart->heart_user_id,
            'status'        => $payload['status'],
            'have_read'     => true,
        ]);
    }

    /**
     * @group updateHeart
     *
     */
    public function testCannotUpdateHeartWithInvalidStatus()
    {
        $n = rand(0, 9);

        while (in_array($n, [HeartStatus::APPROVED, HeartStatus::DENIED])) {
            $n = rand(0, 9);
        }

        $payload = [
            'status' => $n
        ];

        $this->updateHeart($payload)
            ->assertStatus(422);
    }

    /**
     * @group updateHeart
     *
     */
    public function testCannotUpdateHeartIfNotFound()
    {
        $me = $this->newUser(true);

        $token = $me->token;

        $user = factory(User::class)->create();

        $payload = [
            'status' => HeartStatus::APPROVED
        ];

        $this->callHttpWithToken('PUT', 'hearts/' . $user->id, $token, $payload)
            ->assertStatus(404);
    }

    /**
     * @group deleteHeart
     *
     */
    public function testDeleteHeart()
    {
        $me = $this->newUser(true);

        $token = $me->token;

        $book = factory(Book::class)->create();
        $bookshelf = factory(Bookshelf::class)->create();

        $user = factory(User::class)->create();
        $user->books()->attach($book);

        $response = $this->callHttpWithToken('DELETE', 'hearts/' . $user->id, $token);
        $response->assertStatus(403);

        $this->assertEquals($response->getData(), 'have_not_liked_user');

        factory('App\Heart')->create([
            'user_id'       => $user->id,
            'heart_user_id' => $me->user->id,
            'book_id'       => $book->id
        ]);

        $this->assertDatabaseHas('hearts', [
            'user_id'       => $user->id,
            'heart_user_id' => $me->user->id,
            'book_id'       => $book->id
        ]);

        $response = $this->callHttpWithToken('DELETE', 'hearts/' . $user->id, $token);
        $response->assertStatus(200);

        $this->assertDatabaseHas('hearts', [
            'user_id'       => $user->id,
            'heart_user_id' => $me->user->id,
            'book_id'       => $book->id,
            'status'        => HeartStatus::DENIED,
        ]);

        factory('App\Heart')->create([
            'user_id'       => $me->user->id,
            'heart_user_id' => $user->id,
            'book_id'       => $book->id
        ]);

        $this->assertDatabaseHas('hearts', [
            'user_id'       => $me->user->id,
            'heart_user_id' => $user->id,
            'book_id'       => $book->id
        ]);

        $response = $this->callHttpWithToken('DELETE', 'hearts/' . $user->id, $token);
        $response->assertStatus(200);

        $this->assertDatabaseMissing('hearts', [
            'user_id'       => $me->user->id,
            'heart_user_id' => $user->id,
            'book_id'       => HeartStatus::DENIED,
        ]);
    }

    /**
     * @group getNotifications
     *
     */
    public function testGetNotifications()
    {
        $me = $this->newUser(true);

        $token = $me->token;

        $hearts = factory(\App\Heart::class, rand(1, 3))->create(['heart_user_id' => $me->user->id]);

        $response = $this->callHttpWithToken('GET', 'notifications', $token)
            ->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'createdAt',
                    'status',
                    'haveRead',
                    'user' => [
                        'id',
                        'name',
                        'slug',
                    ],
                    'book' => [
                        'id',
                        'title',
                        'slug',
                    ]
                ]
            ]);

        $this->assertEquals($hearts->count(), count($response->getdata()));

        foreach ($hearts as $heart) {
            $response->assertJsonFragment([
                'id'   => $heart->user->id,
                'name' => $heart->user->name,
                'slug' => $heart->user->slug,
            ]);

            $this->assertNotEmpty($heart->user->book);
        }
    }

    /**
     * @group getCountNotifications
     *
     */
    public function testGetCountNotifications()
    {
        $me = $this->newUser(true);

        $token = $me->token;

        $hearts = factory('App\Heart', rand(1, 3))->create(['heart_user_id' => $me->user->id]);

        factory('App\Heart', rand(1, 3))->create([
            'heart_user_id' => $me->user->id,
            'status' => HeartStatus::APPROVED,
        ])->each(function ($heart) {
            $heart->messages()->save(factory('App\Message')->create(['user_id' => $heart->user_id]));
        });

        $approvedHearts = \App\Heart::where('user_id', $me->user->id)
            ->orWhere('heart_user_id', $me->user->id)
            ->where('status', HeartStatus::APPROVED)
            ->pluck('id')
            ->toArray();

        $messages = \App\Message::whereIn('heart_id', $approvedHearts)
            ->where('user_id', '!=', $me->user->id)
            ->where('have_read', false);

        $response = $this->callHttpWithToken('GET', 'notifications/count', $token)
            ->assertStatus(200)
            ->assertJson([
                'count' => [
                    'hearts'   => $hearts->count(),
                    'messages' => $messages->count(),
                ]
            ]);
    }

    /**
     * @group getCountEmptyNotifications
     *
     */
    public function testGetCountEmptyNotifications()
    {
        $me = $this->newUser(true);

        $token = $me->token;

        $response = $this->callHttpWithToken('GET', 'notifications/count', $token)
            ->assertStatus(200)
            ->assertJson([
                'count' => [
                    'hearts'   => 0,
                    'messages' => 0,
                ]
            ]);
    }
}
