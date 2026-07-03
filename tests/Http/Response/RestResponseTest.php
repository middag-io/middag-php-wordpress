<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Http\Response;

use Middag\WordPress\Http\Response\RestResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WP_REST_Response;

/**
 * @internal
 *
 * @coversNothing
 */
final class RestResponseTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Success responses
    // -------------------------------------------------------------------------

    #[Test]
    public function successReturns200WithEnvelope(): void
    {
        $response = RestResponse::success(['id' => 1]);

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertSame(200, $response->get_status());

        $data = $response->get_data();
        self::assertTrue($data['success']);
        self::assertSame(['id' => 1], $data['data']);
        self::assertNull($data['meta']);
        self::assertNull($data['message']);
        self::assertNull($data['errors']);
    }

    #[Test]
    public function successWithCustomStatusAndMessage(): void
    {
        $response = RestResponse::success(null, 202, 'Accepted');

        self::assertSame(202, $response->get_status());

        $data = $response->get_data();
        self::assertTrue($data['success']);
        self::assertNull($data['data']);
        self::assertSame('Accepted', $data['message']);
    }

    #[Test]
    public function successDefaultsToNullDataWhenOmitted(): void
    {
        $response = RestResponse::success();

        $data = $response->get_data();
        self::assertNull($data['data']);
    }

    #[Test]
    public function createdReturns201(): void
    {
        $response = RestResponse::created(['id' => 42], 'Resource created');

        self::assertSame(201, $response->get_status());

        $data = $response->get_data();
        self::assertTrue($data['success']);
        self::assertSame(['id' => 42], $data['data']);
        self::assertSame('Resource created', $data['message']);
    }

    #[Test]
    public function noContentReturns204WithNullBody(): void
    {
        $response = RestResponse::noContent();

        self::assertSame(204, $response->get_status());
        self::assertNull($response->get_data());
    }

    // -------------------------------------------------------------------------
    // Paginated response
    // -------------------------------------------------------------------------

    #[Test]
    public function paginatedReturns200WithMeta(): void
    {
        $items = [['id' => 1], ['id' => 2]];
        $response = RestResponse::paginated($items, total: 50, perPage: 10, currentPage: 3);

        self::assertSame(200, $response->get_status());

        $data = $response->get_data();
        self::assertTrue($data['success']);
        self::assertSame($items, $data['data']);
        self::assertNull($data['message']);
        self::assertNull($data['errors']);

        $meta = $data['meta'];
        self::assertSame(3, $meta['page']);
        self::assertSame(10, $meta['per_page']);
        self::assertSame(50, $meta['total']);
        self::assertSame(5, $meta['pages']); // ceil(50/10)
    }

    #[Test]
    public function paginatedComputesPagesCorrectlyWithRemainder(): void
    {
        $response = RestResponse::paginated([], total: 51, perPage: 10, currentPage: 1);

        $meta = $response->get_data()['meta'];
        self::assertSame(6, $meta['pages']); // ceil(51/10)
    }

    #[Test]
    public function paginatedHandlesZeroPerPageWithoutDivisionByZero(): void
    {
        $response = RestResponse::paginated([], total: 10, perPage: 0, currentPage: 1);

        $meta = $response->get_data()['meta'];
        self::assertSame(0, $meta['pages']);
    }

    // -------------------------------------------------------------------------
    // Error responses
    // -------------------------------------------------------------------------

    #[Test]
    public function errorReturnsEnvelopeWithCode(): void
    {
        $response = RestResponse::error('CUSTOM_ERROR', 'Something broke', 400, ['field' => 'name']);

        self::assertSame(400, $response->get_status());

        $data = $response->get_data();
        self::assertFalse($data['success']);
        self::assertNull($data['data']);
        self::assertNull($data['meta']);
        self::assertSame('Something broke', $data['message']);
        self::assertSame('CUSTOM_ERROR', $data['errors']['code']);
        self::assertSame('name', $data['errors']['field']);
    }

    #[Test]
    public function errorWithoutExtraErrorsUsesCodeOnly(): void
    {
        $response = RestResponse::error('SOME_CODE', 'msg', 500);

        $errors = $response->get_data()['errors'];
        self::assertSame(['code' => 'SOME_CODE'], $errors);
    }

    #[Test]
    public function unauthorizedReturns401(): void
    {
        $response = RestResponse::unauthorized();

        self::assertSame(401, $response->get_status());

        $data = $response->get_data();
        self::assertFalse($data['success']);
        self::assertSame(RestResponse::ERR_AUTHENTICATION, $data['errors']['code']);
    }

    #[Test]
    public function unauthorizedWithDetail(): void
    {
        $response = RestResponse::unauthorized('Bad token', 'JWT expired');

        $data = $response->get_data();
        self::assertSame('Bad token', $data['message']);
        self::assertSame('JWT expired', $data['errors']['detail']);
    }

    #[Test]
    public function forbiddenReturns403(): void
    {
        $response = RestResponse::forbidden();

        self::assertSame(403, $response->get_status());
        self::assertSame(RestResponse::ERR_AUTHORIZATION, $response->get_data()['errors']['code']);
    }

    #[Test]
    public function notFoundReturns404(): void
    {
        $response = RestResponse::notFound();

        self::assertSame(404, $response->get_status());
        self::assertSame(RestResponse::ERR_NOT_FOUND, $response->get_data()['errors']['code']);
    }

    #[Test]
    public function conflictReturns409(): void
    {
        $response = RestResponse::conflict('Duplicate entry', 'slug already exists');

        self::assertSame(409, $response->get_status());

        $data = $response->get_data();
        self::assertSame(RestResponse::ERR_CONFLICT, $data['errors']['code']);
        self::assertSame('slug already exists', $data['errors']['detail']);
    }

    #[Test]
    public function validationErrorReturns422WithFields(): void
    {
        $fields = ['email' => 'required', 'name' => 'too_short'];
        $response = RestResponse::validationError($fields);

        self::assertSame(422, $response->get_status());

        $data = $response->get_data();
        self::assertSame(RestResponse::ERR_VALIDATION, $data['errors']['code']);
        self::assertSame($fields, $data['errors']['fields']);
    }

    #[Test]
    public function rateLimitReturns429WithRetryAfterHeader(): void
    {
        $response = RestResponse::rateLimit('Slow down', 120);

        self::assertSame(429, $response->get_status());

        $data = $response->get_data();
        self::assertSame(RestResponse::ERR_RATE_LIMIT, $data['errors']['code']);

        $headers = $response->get_headers();
        self::assertSame('120', $headers['Retry-After']);
    }

    #[Test]
    public function internalErrorReturns500(): void
    {
        $response = RestResponse::internalError();

        self::assertSame(500, $response->get_status());
        self::assertSame(RestResponse::ERR_INTERNAL, $response->get_data()['errors']['code']);
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    #[Test]
    public function errorCodeConstantsAreDefined(): void
    {
        self::assertSame('VALIDATION_ERROR', RestResponse::ERR_VALIDATION);
        self::assertSame('AUTHENTICATION_ERROR', RestResponse::ERR_AUTHENTICATION);
        self::assertSame('AUTHORIZATION_ERROR', RestResponse::ERR_AUTHORIZATION);
        self::assertSame('NOT_FOUND', RestResponse::ERR_NOT_FOUND);
        self::assertSame('CONFLICT', RestResponse::ERR_CONFLICT);
        self::assertSame('RATE_LIMIT', RestResponse::ERR_RATE_LIMIT);
        self::assertSame('INTERNAL_ERROR', RestResponse::ERR_INTERNAL);
    }
}
