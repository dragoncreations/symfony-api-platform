<?php

namespace App\Tests\Functional;

use App\Entity\ApiToken;
use App\Factory\ApiTokenFactory;
use App\Factory\DragonTreasureFactory;
use App\Factory\UserFactory;
use Zenstruck\Browser\HttpOptions;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class DragonTreasureResourceTest extends ApiTestCase
{
    use ResetDatabase, Factories;

    public function testGetCollectionOfTreasures(): void
    {
        DragonTreasureFactory::createMany(5, [
            'isPublished' => true,
        ]);
        DragonTreasureFactory::createOne([
            'isPublished' => false,
        ]);

        $json = $this->browser()
            ->get('/api/treasures')
            ->assertJson()
            ->assertJsonMatches('totalItems', 5)
            ->assertJsonMatches('length(member)', 5)
            ->json()
        ;

        $this->assertSame(array_keys($json->decoded()['member'][0]), [
            '@id',
            '@type',
            'name',
            'description',
            'value',
            'coolFactor',
            'owner',
            'shortDescription',
            'plunderedAtAgo',
        ]);
    }

    public function testGetOneUnpublishedTreasure404s(): void
    {
        $dragonTreasure = DragonTreasureFactory::createOne([
            'isPublished' => false,
        ]);

        $this->browser()
            ->get('/api/treasures/'.$dragonTreasure->getId())
            ->assertStatus(404);
    }

    public function testPostToCreateTreasure(): void
    {
        $user = UserFactory::createOne(['password' => 'pass']);

        $this->browser()
            ->post('/login', [
                'json' => [
                    'email' => $user->getEmail(),
                    'password' => 'pass',
                ],
            ])
            ->assertStatus(204)
            ->post('/api/treasures', [
                'json' => [],
            ])
            ->assertStatus(422)
            ->post('/api/treasures', HttpOptions::json([
                'name' => 'A shiny thing',
                'description' => 'It sparkles when I wave it in the air.',
                'value' => 1000,
                'coolFactor' => 5,
            ]))
            ->assertStatus(201)
            ->assertJsonMatches('name', 'A shiny thing')
        ;
    }

    public function testPostToCreateTreasureWithApiKey(): void
    {
        $token = ApiTokenFactory::createOne([
            'scopes' => [ApiToken::SCOPE_TREASURE_CREATE]
        ]);

        $this->browser()
            ->post('/api/treasures', [
                'json' => [],
                'headers' => [
                    'Authorization' => 'Bearer ' . $token->getToken()
                ]
            ])
            ->assertStatus(422)
        ;
    }

    public function testPostToCreateTreasureDeniedWithoutScope(): void
    {
        $token = ApiTokenFactory::createOne([
            'scopes' => [ApiToken::SCOPE_TREASURE_EDIT]
        ]);

        $this->browser()
            ->post('/api/treasures', [
                'json' => [],
                'headers' => [
                    'Authorization' => 'Bearer ' . $token->getToken()
                ]
            ])
            ->assertStatus(403)
        ;
    }

    public function testPatchToUpdateTreasure()
    {
        $user = UserFactory::createOne(['password' => 'pass']);
        $treasure = DragonTreasureFactory::createOne(['owner' => $user]);

        $this->browser()
            ->post('/login', [
                'json' => [
                    'email' => $user->getEmail(),
                    'password' => 'pass',
                ],
            ])
            ->patch('/api/treasures/'.$treasure->getId(), [
                'json' => [
                    'value' => 12345,
                ],
            ])
            ->assertStatus(200)
            ->assertJsonMatches('value', 12345)
        ;

        $user2 = UserFactory::createOne(['password' => 'pass2']);
        $this->browser()
            ->post('/login', [
                'json' => [
                    'email' => $user2->getEmail(),
                    'password' => 'pass2',
                ],
            ])
            ->patch('/api/treasures/'.$treasure->getId(), [
                'json' => [
                    'value' => 6789,
                    // be tricky and try to change the owner
                    'owner' => '/api/users/'.$user2->getId(),
                ],
            ])
            ->assertStatus(403)
        ;

        $this->browser()
            ->post('/login', [
                'json' => [
                    'email' => $user->getEmail(),
                    'password' => 'pass',
                ],
            ])
            ->patch('/api/treasures/'.$treasure->getId(), [
                'json' => [
                    // change the owner to someone else
                    'owner' => '/api/users/'.$user2->getId(),
                ],
            ])
            ->assertStatus(422)
        ;
    }

    public function testPatchUnpublishedWorks()
    {
        $user = UserFactory::createOne(['password' => 'pass']);
        $treasure = DragonTreasureFactory::createOne([
            'owner' => $user,
            'isPublished' => false,
        ]);

        $this->browser()
            ->post('/login', [
                'json' => [
                    'email' => $user->getEmail(),
                    'password' => 'pass',
                ],
            ])
            ->patch('/api/treasures/'.$treasure->getId(), [
                'json' => [
                    'value' => 12345,
                ],
            ])
            ->assertStatus(200)
            ->assertJsonMatches('value', 12345)
        ;
    }

    public function testAdminCanPatchToEditTreasure(): void
    {
        $admin = UserFactory::new()->asAdmin()->create(['password' => 'pass']);
        $treasure = DragonTreasureFactory::createOne([
            'isPublished' => true,
        ]);

        $this->browser()
            ->post('/login', [
                'json' => [
                    'email' => $admin->getEmail(),
                    'password' => 'pass',
                ],
            ])
            ->patch('/api/treasures/'.$treasure->getId(), [
                'json' => [
                    'value' => 12345,
                ],
            ])
            ->assertStatus(200)
            ->assertJsonMatches('value', 12345)
            ->assertJsonMatches('isPublished', true)
        ;
    }

    public function testOwnerCanSeeIsPublishedField(): void
    {
        $user = UserFactory::new()->create(['password' => 'pass']);
        $treasure = DragonTreasureFactory::createOne([
            'isPublished' => true,
            'owner' => $user,
        ]);
        $this->browser()
            ->post('/login', [
                'json' => [
                    'email' => $user->getEmail(),
                    'password' => 'pass',
                ],
            ])
            ->patch('/api/treasures/'.$treasure->getId(), [
                'json' => [
                    'value' => 12345,
                ],
            ])
            ->assertStatus(200)
            ->assertJsonMatches('value', 12345)
            ->assertJsonMatches('isPublished', true)
        ;
    }

    public function testOwnerCanSeeIsPublishedAndIsMineFields(): void
    {
        $user = UserFactory::new()->create(['password' => 'pass']);
        $treasure = DragonTreasureFactory::createOne([
            'isPublished' => false,
            'owner' => $user,
        ]);

        $this->browser()
            ->post('/login', [
                'json' => [
                    'email' => $user->getEmail(),
                    'password' => 'pass',
                ],
            ])
            ->patch('/api/treasures/'.$treasure->getId(), [
                'json' => [
                    'value' => 12345,
                ],
            ])
            ->assertStatus(200)
            ->assertJsonMatches('value', 12345)
            ->assertJsonMatches('isPublished', false)
            ->assertJsonMatches('isMine', true)
        ;
    }
}