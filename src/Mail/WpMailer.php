<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Mail;

use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\Framework\Mail\Address;
use Middag\Framework\Mail\Attachment;
use Middag\Framework\Mail\Contract\MailerInterface;
use Middag\Framework\Mail\Mail;
use Middag\WordPress\Support\MailSupport;

/**
 * WordPress implementation of the framework mail port: maps a {@see Mail}
 * value object onto `wp_mail()`.
 *
 * From/Reply-To/Cc/Bcc travel as RFC headers; `htmlBody` switches the
 * Content-Type to text/html (WordPress default is plain text). Attachments are
 * passed as file paths — `cid:` embedded parts are NOT supported by `wp_mail()`
 * and raise an exception instead of silently degrading.
 *
 * @api
 */
final class WpMailer implements MailerInterface
{
    public function send(Mail $mail): void
    {
        $to = array_map(
            static fn (Address $address): string => $address->toString(),
            $mail->to,
        );

        $headers = [];

        if ($mail->from instanceof Address) {
            $headers[] = 'From: ' . $mail->from->toString();
        }

        if ($mail->replyTo instanceof Address) {
            $headers[] = 'Reply-To: ' . $mail->replyTo->toString();
        }

        foreach ($mail->cc as $address) {
            $headers[] = 'Cc: ' . $address->toString();
        }

        foreach ($mail->bcc as $address) {
            $headers[] = 'Bcc: ' . $address->toString();
        }

        if ($mail->htmlBody !== null) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }

        $attachments = array_map(
            static function (Attachment $attachment): string {
                if ($attachment->isEmbedded()) {
                    throw new MiddagInfrastructureException(
                        sprintf('wp_mail() cannot embed "cid:%s" parts; attach the file normally or use another transport.', (string) $attachment->contentId),
                    );
                }

                return $attachment->path;
            },
            $mail->attachments,
        );

        $sent = MailSupport::send(
            $to,
            $mail->subject,
            $mail->htmlBody ?? $mail->body,
            $headers,
            $attachments,
        );

        if (!$sent) {
            throw new MiddagInfrastructureException(
                sprintf('wp_mail() failed to send "%s" to %s.', $mail->subject, implode(', ', $to)),
            );
        }
    }
}
