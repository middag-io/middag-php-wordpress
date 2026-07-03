<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Domain\User;

use Middag\WordPress\Domain\User\UserRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WP_User;

/**
 * @internal
 */
#[CoversClass(UserRepository::class)]
final class UserRepositoryTest extends TestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new UserRepository();
        $GLOBALS['__wp_test_users_by'] = [];
        $GLOBALS['__wp_test_user_queries'] = [];
        $GLOBALS['__wp_test_inserted_users'] = [];
        $GLOBALS['__wp_test_updated_users'] = [];
        $GLOBALS['__wp_test_user_id'] = 0;
        $GLOBALS['__wp_test_caps'] = [];
        unset(
            $GLOBALS['__wp_test_user_query_results'],
            $GLOBALS['__wp_test_user_query_total'],
            $GLOBALS['__wp_test_insert_user_result'],
            $GLOBALS['__wp_test_update_user_result'],
            $GLOBALS['__wp_test_current_user'],
        );
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_users_by'],
            $GLOBALS['__wp_test_user_queries'],
            $GLOBALS['__wp_test_user_query_results'],
            $GLOBALS['__wp_test_user_query_total'],
            $GLOBALS['__wp_test_inserted_users'],
            $GLOBALS['__wp_test_updated_users'],
            $GLOBALS['__wp_test_insert_user_result'],
            $GLOBALS['__wp_test_update_user_result'],
            $GLOBALS['__wp_test_user_id'],
            $GLOBALS['__wp_test_caps'],
            $GLOBALS['__wp_test_current_user'],
        );
    }

    #[Test]
    public function findByIdEmailAndLoginResolveFromTheUsersApi(): void
    {
        $user = new WP_User(7);
        $GLOBALS['__wp_test_users_by']['id']['7'] = $user;
        $GLOBALS['__wp_test_users_by']['email']['ana@example.test'] = $user;
        $GLOBALS['__wp_test_users_by']['login']['ana'] = $user;

        self::assertSame($user, $this->repository->findById(7));
        self::assertSame($user, $this->repository->findByEmail('ana@example.test'));
        self::assertSame($user, $this->repository->findByLogin('ana'));
    }

    #[Test]
    public function findersReturnNullForUnknownUsers(): void
    {
        self::assertNull($this->repository->findById(999));
        self::assertNull($this->repository->findByEmail('ghost@example.test'));
        self::assertNull($this->repository->findByLogin('ghost'));
    }

    #[Test]
    public function currentUserHelpersDelegateToTheSessionSeam(): void
    {
        $user = new WP_User(9);
        $GLOBALS['__wp_test_user_id'] = 9;
        $GLOBALS['__wp_test_current_user'] = $user;
        $GLOBALS['__wp_test_caps']['manage_options'] = true;

        self::assertSame(9, $this->repository->getCurrentUserId());
        self::assertSame($user, $this->repository->getCurrentUser());
        self::assertTrue($this->repository->currentUserCan('manage_options'));
        self::assertFalse($this->repository->currentUserCan('edit_others_posts'));
    }

    #[Test]
    public function queryMergesTheDefaultArguments(): void
    {
        $GLOBALS['__wp_test_user_query_results'] = [new WP_User(1)];

        $results = $this->repository->query(['role' => 'editor']);

        self::assertCount(1, $results);
        $vars = $GLOBALS['__wp_test_user_queries'][0];
        self::assertSame(20, $vars['number']);
        self::assertSame('registered', $vars['orderby']);
        self::assertSame('DESC', $vars['order']);
        self::assertSame('editor', $vars['role']);
    }

    #[Test]
    public function queryArgsOverrideTheDefaults(): void
    {
        $this->repository->query(['number' => 5, 'order' => 'ASC']);

        $vars = $GLOBALS['__wp_test_user_queries'][0];
        self::assertSame(5, $vars['number']);
        self::assertSame('ASC', $vars['order']);
    }

    #[Test]
    public function countRequestsTheTotalWithoutFetchingRows(): void
    {
        $GLOBALS['__wp_test_user_query_total'] = 37;

        self::assertSame(37, $this->repository->count(['role' => 'subscriber']));

        $vars = $GLOBALS['__wp_test_user_queries'][0];
        self::assertTrue($vars['count_total']);
        self::assertSame(0, $vars['number']);
    }

    #[Test]
    public function searchWrapsTheTermAndTargetsTheSearchColumns(): void
    {
        $this->repository->search('ana', 10);

        $vars = $GLOBALS['__wp_test_user_queries'][0];
        self::assertSame('*ana*', $vars['search']);
        self::assertSame(['user_login', 'user_email', 'display_name'], $vars['search_columns']);
        self::assertSame(10, $vars['number']);
    }

    #[Test]
    public function findByRoleQueriesTheRoleWithoutALimitByDefault(): void
    {
        $this->repository->findByRole('editor');

        $vars = $GLOBALS['__wp_test_user_queries'][0];
        self::assertSame('editor', $vars['role']);
        self::assertSame(-1, $vars['number']);
    }

    #[Test]
    public function createDefaultsTheLoginToTheEmailAndTheRoleToSubscriber(): void
    {
        $GLOBALS['__wp_test_insert_user_result'] = 55;

        $id = $this->repository->create('ana@example.test', 's3cret');

        self::assertSame(55, $id);
        $data = $GLOBALS['__wp_test_inserted_users'][0];
        self::assertSame('ana@example.test', $data['user_login']);
        self::assertSame('ana@example.test', $data['user_email']);
        self::assertSame('s3cret', $data['user_pass']);
        self::assertSame('subscriber', $data['role']);
    }

    #[Test]
    public function createHonoursExplicitUserData(): void
    {
        $this->repository->create('ana@example.test', 's3cret', [
            'user_login' => 'ana',
            'role' => 'editor',
        ]);

        $data = $GLOBALS['__wp_test_inserted_users'][0];
        self::assertSame('ana', $data['user_login']);
        self::assertSame('editor', $data['role']);
    }

    #[Test]
    public function updateInjectsTheUserIdIntoTheData(): void
    {
        $result = $this->repository->update(7, ['display_name' => 'Ana']);

        self::assertSame(7, $result);
        self::assertSame(
            ['display_name' => 'Ana', 'ID' => 7],
            $GLOBALS['__wp_test_updated_users'][0],
        );
    }
}
