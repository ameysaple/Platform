<?php

namespace Modules\User\Tests;

use Illuminate\Support\Facades\Event;
use Modules\User\Entities\Sentinel\User;
use Modules\User\Events\UserHasRegistered;
use Modules\User\Events\UserIsUpdating;
use Modules\User\Events\UserWasCreated;
use Modules\User\Events\UserWasUpdated;
use Modules\User\Exceptions\UserNotFoundException;
use Modules\User\Repositories\RoleRepository;
use Modules\User\Repositories\UserRepository;

class SentinelUserRepositoryTest extends BaseUserTestCase
{
    /**
     * @var RoleRepository
     */
    private $role;
    /**
     * @var UserRepository
     */
    private $user;

    public function setUp()
    {
        parent::setUp();
        $this->role = app(RoleRepository::class);
        $this->user = app(UserRepository::class);
    }

    /** @test */
    public function it_creates_a_new_user()
    {
        $this->user->create([
            'email' => 'n.widart@gmail.com',
            'password' => 'demo1234',
        ]);

        $user = $this->user->find(1);

        $this->assertInstanceOf(User::class, $user);
        $this->assertCount(1, $this->user->all());
    }

    /** @test */
    public function it_fires_event_when_user_created()
    {
        Event::fake();

        $user = $this->user->create([
            'email' => 'n.widart@gmail.com',
            'password' => 'demo1234',
        ]);

        Event::assertDispatched(UserHasRegistered::class, function ($e) use ($user) {
            return $e->user->id === $user->id;
        });
    }

    /** @test */
    public function it_fires_event_when_user_has_registered()
    {
        Event::fake();

        $user = $this->user->create([
            'email' => 'n.widart@gmail.com',
            'password' => 'demo1234',
        ], true);

        Event::assertDispatched(UserWasCreated::class, function ($e) use ($user) {
            return $e->user->id === $user->id;
        });
    }

    /** @test */
    public function it_hashes_user_password()
    {
        $this->createRole('User');

        $userOne = $this->user->create([
            'email' => 'n.widart@gmail.com',
            'password' => 'demo1234',
        ]);
        $userTwo = $this->user->createWithRoles([
            'email' => 'jane@doe.com',
            'password' => 'demo1234',
        ], ['User']);
        $userThree = $this->user->createWithRolesFromCli([
            'email' => 'john@doe.com',
            'password' => 'demo1234',
        ], ['User']);

        $this->assertNotEquals('demo1234', $userOne->password);
        $this->assertNotEquals('demo1234', $userTwo->password);
        $this->assertNotEquals('demo1234', $userThree->password);
    }

    /** @test */
    public function it_creates_user_with_given_role()
    {
        $this->createRole('User');

        $user = $this->user->createWithRoles([
            'email' => 'n.widart@gmail.com',
            'password' => 'demo1234',
        ], ['User']);

        $this->assertInstanceOf(User::class, $user);
        $this->assertCount(1, $this->user->all());
    }

    /** @test */
    public function it_creates_user_without_triggering_events_for_cli()
    {
        Event::fake();

        $this->user->createWithRolesFromCli([
            'email' => 'john@doe.com',
            'password' => 'demo1234',
        ], ['User']);

        Event::assertNotDispatched(UserWasCreated::class);
        Event::assertNotDispatched(UserHasRegistered::class);
    }

    /** @test */
    public function it_creates_new_user_with_api_keys()
    {
        $user = $this->user->create([
            'email' => 'n.widart@gmail.com',
            'password' => 'demo1234',
        ]);

        $this->assertCount(1, $user->api_keys);
    }

    /** @test */
    public function it_updates_a_user()
    {
        $user = $this->user->create([
            'email' => 'n.widart@gmail.com',
            'password' => 'demo1234',
        ]);

        $this->user->update($user, ['first_name' => 'John', 'last_name' => 'Doe']);

        $this->assertEquals('John', $user->first_name);
        $this->assertEquals('Doe', $user->last_name);
    }

    /** @test */
    public function it_triggers_events_on_user_update()
    {
        $user = $this->user->create([
            'email' => 'n.widart@gmail.com',
            'password' => 'demo1234',
        ]);

        Event::fake();

        $this->user->update($user, ['first_name' => 'John', 'last_name' => 'Doe']);

        Event::assertDispatched(UserWasUpdated::class, function ($e) use ($user) {
            return $e->user->id === $user->id;
        });
        Event::assertDispatched(UserIsUpdating::class, function ($e) use ($user) {
            return $e->user->id === $user->id;
        });
    }

    /** @test */
    public function it_updates_user_and_syncs_roles()
    {
        $this->createRole('User');
        $this->createRole('Admin');
        $user = $this->user->createWithRoles([
            'email' => 'n.widart@gmail.com',
            'password' => 'demo1234',
        ], [1]);

        $this->user->updateAndSyncRoles($user->id, ['first_name' => 'John', 'last_name' => 'Doe', 'activated' => 1], [2]);

        $user->refresh();

        $this->assertEquals('John', $user->first_name);
        $this->assertEquals('Doe', $user->last_name);
        $this->assertCount(1, $user->roles);
    }

    /** @test */
    public function it_triggers_event_on_user_update_and_role_sync()
    {
        $this->createRole('User');
        $this->createRole('Admin');
        $user = $this->user->createWithRoles([
            'email' => 'n.widart@gmail.com',
            'password' => 'demo1234',
        ], [1]);
        Event::fake();

        $this->user->updateAndSyncRoles($user->id, ['first_name' => 'John', 'last_name' => 'Doe', 'activated' => 1], [2]);

        Event::assertDispatched(UserWasUpdated::class, function ($e) use ($user) {
            return $e->user->id === $user->id;
        });
        Event::assertDispatched(UserIsUpdating::class, function ($e) use ($user) {
            return $e->user->id === $user->id;
        });
    }

    /** @test */
    public function it_deletes_a_user()
    {
        $this->user->create([
            'email' => 'n.widart@gmail.com',
            'password' => 'demo1234',
        ]);

        $this->assertCount(1, $this->user->all());
        $this->user->delete(1);
        $this->assertCount(0, $this->user->all());
    }

    /** @test */
    public function it_throws_exception_if_user_not_found_when_deleting()
    {
        $this->expectException(UserNotFoundException::class);

        $this->user->delete(1);
    }

    /** @test */
    public function it_finds_a_user_by_its_credentials()
    {
        $this->user->create([
            'email' => 'n.widart@gmail.com',
            'password' => 'demo1234',
        ]);

        $user = $this->user->findByCredentials([
            'email' => 'n.widart@gmail.com',
            'password' => 'demo1234',
        ]);

        $this->assertEquals('n.widart@gmail.com', $user->email);
    }

    private function createRole($name)
    {
        return $this->role->create([
            'name' => $name,
            'slug' => str_slug($name),
        ]);
    }
}
