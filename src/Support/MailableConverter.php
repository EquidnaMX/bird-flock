<?php

/**
 * Converts Laravel Mailables to FlightPlan DTOs.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Support;

use Equidna\BirdFlock\DTO\FlightPlan;
use Illuminate\Contracts\Mail\Mailable as MailableContract;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\View;

/**
 * Converts Laravel Mailable instances to FlightPlan DTOs.
 */
final class MailableConverter
{
    /**
     * Convert a Laravel Mailable to a FlightPlan.
     *
     * @param  Mailable|MailableContract $mailable        The mailable instance to convert.
     * @param  string                     $to              Recipient email address.
     * @param  string|null                $idempotencyKey  Optional idempotency key.
     * @param  \DateTimeInterface|null    $sendAt          Optional scheduled send time.
     * @param  array<string, mixed>       $metadata        Additional metadata.
     * @return FlightPlan                                  The converted flight plan.
     */
    public static function convert(
        Mailable|MailableContract $mailable,
        string $to,
        ?string $idempotencyKey = null,
        ?\DateTimeInterface $sendAt = null,
        array $metadata = []
    ): FlightPlan {
        // Build the mailable to populate its properties
        $mailable->build();

        // Extract subject from mailable
        $subject = $mailable->subject ?? null;

        // Render the mailable views
        $html = null;
        $text = null;

        // Check if mailable has a view
        if (isset($mailable->view) && $mailable->view) {
            $html = self::renderView($mailable->view, $mailable->buildViewData());
        }

        // Check if mailable has a text view
        if (isset($mailable->textView) && $mailable->textView) {
            $text = self::renderView($mailable->textView, $mailable->buildViewData());
        }

        // If no text view but has HTML, extract text from HTML as fallback
        if ($html && !$text) {
            $text = self::htmlToText($html);
        }

        // Handle attachments by encoding them in metadata
        $attachments = [];
        
        if (method_exists($mailable, 'attachments') && is_callable([$mailable, 'attachments'])) {
            $mailableAttachments = $mailable->attachments();
            
            if (is_array($mailableAttachments)) {
                foreach ($mailableAttachments as $attachment) {
                    if (is_object($attachment) && method_exists($attachment, 'toArray')) {
                        $attachmentData = $attachment->toArray();
                    } elseif (is_array($attachment)) {
                        $attachmentData = $attachment;
                    } else {
                        continue;
                    }
                    
                    // Read file content if path is provided
                    if (isset($attachmentData['path']) && file_exists($attachmentData['path'])) {
                        $content = file_get_contents($attachmentData['path']);
                        $attachments[] = [
                            'content' => base64_encode($content),
                            'filename' => $attachmentData['name'] ?? basename($attachmentData['path']),
                            'type' => $attachmentData['mime'] ?? mime_content_type($attachmentData['path']) ?: 'application/octet-stream',
                        ];
                    }
                }
            }
        }

        // Merge attachments into metadata
        if (!empty($attachments)) {
            $metadata['attachments'] = $attachments;
        }

        return new FlightPlan(
            channel: 'email',
            to: $to,
            subject: $subject,
            text: $text,
            html: $html,
            metadata: $metadata,
            idempotencyKey: $idempotencyKey,
            sendAt: $sendAt,
        );
    }

    /**
     * Render a view with data.
     *
     * @param  string              $view View name.
     * @param  array<string,mixed> $data View data.
     * @return string                    Rendered HTML.
     */
    private static function renderView(string $view, array $data): string
    {
        return View::make($view, $data)->render();
    }

    /**
     * Convert HTML to plain text by stripping tags.
     *
     * @param  string $html HTML content.
     * @return string       Plain text content.
     */
    private static function htmlToText(string $html): string
    {
        // Remove script and style tags with their content
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
        
        // Convert line breaks to newlines
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        
        // Strip all HTML tags
        $text = strip_tags($html);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Normalize whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s+\n/', "\n\n", $text);
        $text = trim($text);
        
        return $text;
    }
}
