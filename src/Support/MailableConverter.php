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
use Equidna\BirdFlock\Support\Logger;
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

        // Get view data using reflection to access protected buildViewData method
        $viewData = self::getViewData($mailable);

        // Render the mailable views
        $html = null;
        $text = null;
        $inlineMessage = new InlineAttachmentMessage();

        // Check if mailable has a view
        if (isset($mailable->view) && $mailable->view) {
            $html = self::renderView($mailable->view, $viewData, $inlineMessage);
        }

        // Check if mailable has a text view
        if (isset($mailable->textView) && $mailable->textView) {
            $text = self::renderView($mailable->textView, $viewData);
        }

        // If no text view but has HTML, extract text from HTML as fallback
        if ($html && !$text) {
            $text = self::htmlToText($html);
        }

        // Handle attachments by encoding them in metadata
        $attachments = array_merge(
            self::extractAttachments($mailable),
            $inlineMessage->attachments()
        );

        // Merge attachments into metadata
        if (!empty($attachments)) {
            $metadata['attachments'] = array_merge(
                $metadata['attachments'] ?? [],
                $attachments
            );
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
     * Get view data from mailable using reflection.
     *
     * @param  Mailable|MailableContract $mailable The mailable instance.
     * @return array<string,mixed>                 View data array.
     */
    private static function getViewData(Mailable|MailableContract $mailable): array
    {
        try {
            // Try to call buildViewData if it's accessible
            $reflection = new \ReflectionClass($mailable);
            $method = $reflection->getMethod('buildViewData');
            $method->setAccessible(true);
            return $method->invoke($mailable);
        } catch (\ReflectionException $e) {
            // Fallback: return public properties
            $data = [];
            $reflection = new \ReflectionClass($mailable);
            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                if (!$property->isStatic()) {
                    $data[$property->getName()] = $property->getValue($mailable);
                }
            }
            return $data;
        }
    }

    /**
     * Render a view with data.
     *
     * @param  string              $view View name.
     * @param  array<string,mixed> $data View data.
     * @param  InlineAttachmentMessage|null $message Inline attachment helper.
     * @return string                    Rendered HTML.
     */
    private static function renderView(string $view, array $data, ?InlineAttachmentMessage $message = null): string
    {
        if ($message !== null) {
            $data['message'] = $message;
        }

        return View::make($view, $data)->render();
    }

    /**
     * Extract attachments from mailable.
     *
     * @param  Mailable|MailableContract $mailable The mailable instance.
     * @return array<array<string,string>>        Array of attachment data.
     */
    private static function extractAttachments(Mailable|MailableContract $mailable): array
    {
        $attachments = [];

        // Access the attachments property via reflection
        try {
            $reflection = new \ReflectionClass($mailable);
            
            // Laravel's Mailable stores attachments in a protected property
            if ($reflection->hasProperty('attachments')) {
                $property = $reflection->getProperty('attachments');
                $property->setAccessible(true);
                $mailableAttachments = $property->getValue($mailable);

                if (is_array($mailableAttachments)) {
                    foreach ($mailableAttachments as $attachment) {
                        $attachmentData = null;
                        
                        // Handle different attachment formats
                        if (is_object($attachment) && method_exists($attachment, 'toArray')) {
                            $attachmentData = $attachment->toArray();
                        } elseif (is_array($attachment)) {
                            $attachmentData = $attachment;
                        }

                        if ($attachmentData && isset($attachmentData['file']) && file_exists($attachmentData['file'])) {
                            $filePath = $attachmentData['file'];
                            $content = file_get_contents($filePath);
                            
                            $attachments[] = [
                                'content' => base64_encode($content),
                                'filename' => $attachmentData['options']['as'] ?? basename($filePath),
                                'type' => $attachmentData['options']['mime'] ?? mime_content_type($filePath) ?: 'application/octet-stream',
                            ];
                        }
                    }
                }
            }
        } catch (\ReflectionException $e) {
            // If we can't access attachments, just skip them
            Logger::warning('bird-flock.mailable.attachments_skipped', [
                'error' => $e->getMessage(),
            ]);
        }

        return $attachments;
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

/**
 * Minimal view helper for Laravel-style inline attachments.
 */
final class InlineAttachmentMessage
{
    /**
     * @var array<int,array<string,string>>
     */
    private array $attachments = [];

    /**
     * Embed a file attachment and return its CID reference.
     *
     * @param  string $path File path.
     * @return string       CID reference for the HTML view.
     */
    public function embed(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException("Inline attachment file is not readable: {$path}");
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new \InvalidArgumentException("Inline attachment file could not be read: {$path}");
        }

        $filename = basename($path);

        $this->attachments[] = [
            'content' => base64_encode($content),
            'filename' => $filename,
            'type' => mime_content_type($path) ?: 'application/octet-stream',
            'disposition' => 'inline',
            'content_id' => $filename,
        ];

        return "cid:{$filename}";
    }

    /**
     * Embed in-memory data and return its CID reference.
     *
     * @param  string $data Raw attachment data.
     * @param  string $name Attachment name and content ID.
     * @return string       CID reference for the HTML view.
     */
    public function embedData(string $data, string $name): string
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Inline attachment name cannot be empty');
        }

        $this->attachments[] = [
            'content' => base64_encode($data),
            'filename' => $name,
            'type' => 'application/octet-stream',
            'disposition' => 'inline',
            'content_id' => $name,
        ];

        return "cid:{$name}";
    }

    /**
     * Return collected inline attachments.
     *
     * @return array<int,array<string,string>>
     */
    public function attachments(): array
    {
        return $this->attachments;
    }
}
