<?php

namespace App\Tests\Functional;

use App\Factory\DragonTreasureFactory;
use App\Factory\UserFactory;
use Zenstruck\Browser\Json;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserResourceTest extends ApiTestCase
{
    use ResetDatabase;
    use Factories;

    public function testPostToCreateUser(): void
    {
        $this->browser()
            ->post('/api/users', [
                'json' => [
                    'email' => 'draggin_in_the_morning@coffee.com',
                    'username' => 'draggin_in_the_morning',
                    'password' => 'password',
                ]
            ])
            ->assertStatus(201)
            ->use(function (Json $json) {
                $json->assertMissing('password');
                $json->assertMissing('id');
            })
            ->post('/login', [
                'json' => [
                    'email' => 'draggin_in_the_morning@coffee.com',
                    'password' => 'password',
                ]
            ])
            ->assertSuccessful()
        ;
    }

    public function testPatchToUpdateUser(): void
    {
        $user = UserFactory::createOne(['password' => 'pass']);

        $this->browser()
            ->post('/login', [
                'json' => [
                    'email' => $user->getEmail(),
                    'password' => 'pass',
                ],
            ])
            ->patch('/api/users/' . $user->getId(), [
                'json' => [
                    'username' => 'changed',
                ],
                'headers' => ['Content-Type' => 'application/merge-patch+json']
            ])
            ->assertStatus(200);
    }

    public function testTreasuresCanBeRemoved(): void
    {
        $user = UserFactory::createOne(['password' => 'pass']);
        $otherUser = UserFactory::createOne();
        $dragonTreasure = DragonTreasureFactory::createOne(['owner' => $user]);
        DragonTreasureFactory::createOne(['owner' => $user]);
        $dragonTreasure3 = DragonTreasureFactory::createOne(['owner' => $otherUser]);

        $this->browser()
            ->post('/login', [
                'json' => [
                    'email' => $user->getEmail(),
                    'password' => 'pass',
                ],
            ])
            ->patch('/api/users/' . $user->getId(), [
                'json' => [
                    'dragonTreasures' => [
                        '/api/treasures/' . $dragonTreasure->getId(),
                        '/api/treasures/' . $dragonTreasure3->getId(),
                    ],
                ],
                'headers' => ['Content-Type' => 'application/merge-patch+json']
            ])
            ->assertStatus(200)
            ->get('/api/users/' . $user->getId())
            ->dump()
            ->assertJsonMatches('length("dragonTreasures")', 2)
            ->assertJsonMatches('dragonTreasures[0]', '/api/treasures/' . $dragonTreasure->getId())
            ->assertJsonMatches('dragonTreasures[1]', '/api/treasures/' . $dragonTreasure3->getId())
        ;
    }

    public function testTreasuresCannotBeStolen(): void
    {
        $user = UserFactory::createOne(['password' => 'pass']);
        $otherUser = UserFactory::createOne();
        $dragonTreasure = DragonTreasureFactory::createOne(['owner' => $otherUser]);

        $this->browser()
            ->post('/login', [
                'json' => [
                    'email' => $user->getEmail(),
                    'password' => 'pass',
                ],
            ])
            ->patch('/api/users/' . $user->getId(), [
                'json' => [
                    'username' => 'changed',
                    'dragonTreasures' => [
                        '/api/treasures/' . $dragonTreasure->getId(),
                    ],
                ],
                'headers' => ['Content-Type' => 'application/merge-patch+json']
            ])
            ->assertStatus(422);
    }

    public function testUnpublishedTreasuresNotReturned(): void
    {
        $user = UserFactory::createOne();
        DragonTreasureFactory::createOne([
            'isPublished' => false,
            'owner' => $user,
        ]);

        $user2 = UserFactory::createOne(['password' => 'pass2']);

        $this->browser()
            ->post('/login', [
                'json' => [
                    'email' => $user2->getEmail(),
                    'password' => 'pass2',
                ],
            ])
            ->get('/api/users/' . $user->getId())
            ->assertJsonMatches('length("dragonTreasures")', 0);
    }
}