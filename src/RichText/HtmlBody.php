<?php

namespace Coderflex\LaravelTicket\RichText;

use Coderflex\LaravelTicket\Models\Attachment;
use Coderflex\LaravelTicket\Models\Ticket;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Illuminate\Support\Facades\Storage;

class HtmlBody
{
    /**
     * Keep a conservative set of tags. Everything else is unwrapped.
     *
     * @var array<string, string>
     */
    private array $tags = [
        'p' => 'p',
        'br' => 'br',
        'b' => 'strong',
        'strong' => 'strong',
        'i' => 'em',
        'em' => 'em',
        'ul' => 'ul',
        'ol' => 'ol',
        'li' => 'li',
        'a' => 'a',
        'img' => 'img',
        'blockquote' => 'blockquote',
        'code' => 'code',
        'pre' => 'pre',
        'h2' => 'h2',
        'h3' => 'h3',
        'div' => 'p',
    ];

    /**
     * Sanitize HTML for storage. Images must be unclaimed uploads on this ticket.
     */
    public function clean(string $html, int $ticketId): string
    {
        return $this->filter($html, $ticketId, true);
    }

    /**
     * Sanitize HTML again for display. Images already saved on the ticket stay.
     */
    public function render(string $html, int $ticketId): string
    {
        return $this->filter($html, $ticketId, false);
    }

    public function isEmpty(string $html): bool
    {
        if (str_contains($html, '<img')) {
            return false;
        }

        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $text === '';
    }

    /**
     * A single paragraph with no formatting is stored as plain text.
     */
    public function asPlain(string $html): ?string
    {
        if (preg_match('#^<p>([^<]*)</p>$#', $html, $matches) === 1) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if ($html !== '' && ! str_contains($html, '<')) {
            return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    }

    public function toText(string $html): string
    {
        $text = preg_replace('#<(br|/p|/li|/h2|/h3|/blockquote)>#i', "\n", $html);
        $text = is_string($text) ? $text : $html;
        $text = html_entity_decode(strip_tags(str_replace('<img', "\n[photo] <img", $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim(is_string($text) ? $text : '');
    }

    /**
     * Replace ticket image URLs with cid: references for outbound mail.
     *
     * @return array{html: string, text: string, inline: list<array{cid: string, bytes: string, mime: string, name: string}>}
     */
    public function embed(string $html, Ticket $ticket): array
    {
        $inline = [];
        $embedded = preg_replace_callback('/<img\b[^>]*>/i', function (array $matches) use ($ticket, &$inline) {
            $tag = $matches[0];

            if (preg_match('/\bsrc="([^"]*)"/i', $tag, $src) !== 1) {
                return '';
            }

            $id = $this->attachmentId(html_entity_decode($src[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), (int) $ticket->getKey());
            $file = $id === null ? null : $this->file($id, (int) $ticket->getKey(), false);

            if (! $file instanceof Attachment) {
                return '';
            }

            $bytes = Storage::disk($file->disk)->get($file->path);

            if (! is_string($bytes) || $bytes === '') {
                return '';
            }

            $cid = 'att'.$file->getKey().'@ticket'.$ticket->getKey();
            $inline[] = [
                'cid' => $cid,
                'bytes' => $bytes,
                'mime' => is_string($file->mime) && $file->mime !== '' ? $file->mime : 'application/octet-stream',
                'name' => $file->original_name !== '' ? $file->original_name : 'image',
            ];

            return '<img src="cid:'.$cid.'" alt="'.htmlspecialchars((string) $file->original_name, ENT_QUOTES | ENT_HTML5, 'UTF-8').'">';
        }, $html);

        $html = is_string($embedded) ? $embedded : $html;

        return [
            'html' => $html,
            'text' => $this->toText($html),
            'inline' => $inline,
        ];
    }

    /**
     * @param  list<int>  $ids
     */
    public function claim(Ticket $ticket, array $ids, int $messageId): void
    {
        if ($ids === []) {
            return;
        }

        Attachment::query()
            ->where('ticket_id', $ticket->getKey())
            ->whereIn('id', $ids)
            ->whereNull('message_id')
            ->update([
                'message_id' => $messageId,
                'is_inline' => true,
            ]);
    }

    /**
     * @return list<int>
     */
    public function imageIds(string $html, int $ticketId): array
    {
        preg_match_all('/\bsrc="([^"]*)"/i', $html, $matches);
        $ids = [];

        foreach ($matches[1] as $src) {
            $id = $this->attachmentId(html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $ticketId);

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function filter(string $html, int $ticketId, bool $claiming): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $source = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $source->loadHTML(
            '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>'.$html.'</body></html>',
            LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $source->getElementsByTagName('body')->item(0);

        if (! $body instanceof DOMNode) {
            return '';
        }

        $clean = new DOMDocument('1.0', 'UTF-8');
        $root = $clean->createElement('body');
        $clean->appendChild($root);
        $this->walk($body, $root, $ticketId, $claiming);

        return $this->serialize($root);
    }

    private function walk(DOMNode $source, DOMNode $target, int $ticketId, bool $claiming): void
    {
        foreach ($source->childNodes as $child) {
            if ($child instanceof DOMText) {
                $document = $target->ownerDocument;

                if ($document instanceof DOMDocument) {
                    $target->appendChild($document->createTextNode($child->data));
                }

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $mapped = $this->tags[strtolower($child->nodeName)] ?? null;

            if ($mapped === null) {
                $this->walk($child, $target, $ticketId, $claiming);

                continue;
            }

            $document = $target->ownerDocument;

            if (! $document instanceof DOMDocument) {
                continue;
            }

            $element = $document->createElement($mapped);

            if (! $this->keep($child, $element, $ticketId, $claiming)) {
                $this->walk($child, $target, $ticketId, $claiming);

                continue;
            }

            if (! in_array($mapped, ['br', 'img'], true)) {
                $this->walk($child, $element, $ticketId, $claiming);
            }

            if ($this->blank($element)) {
                continue;
            }

            $target->appendChild($element);
        }
    }

    private function keep(DOMElement $source, DOMElement $target, int $ticketId, bool $claiming): bool
    {
        $tag = $target->nodeName;

        if ($tag === 'a') {
            $href = trim($source->getAttribute('href'));

            if (preg_match('#^(https?://|mailto:)#i', $href) !== 1 || preg_match('#[\s\x00-\x1f]#', $href) === 1) {
                return false;
            }

            $target->setAttribute('href', $href);
            $target->setAttribute('rel', 'noopener noreferrer');

            return true;
        }

        if ($tag === 'img') {
            $id = $this->attachmentId($source->getAttribute('src'), $ticketId);
            $file = $id === null ? null : $this->file($id, $ticketId, $claiming);

            if (! $file instanceof Attachment) {
                return false;
            }

            $target->setAttribute('src', route('laravel-ticket.tickets.attachments.show', [
                'ticket' => $ticketId,
                'attachment' => $file->getKey(),
            ], false));
            $alt = trim(preg_replace('/\s+/', ' ', $source->getAttribute('alt')) ?? '');
            $target->setAttribute('alt', $alt !== '' ? mb_substr($alt, 0, 200) : $file->original_name);

            return true;
        }

        return true;
    }

    private function file(int $id, int $ticketId, bool $claiming): ?Attachment
    {
        $query = Attachment::query()->whereKey($id)->where('ticket_id', $ticketId);

        if ($claiming) {
            $query->whereNull('message_id');
        }

        $file = $query->first();

        return $file instanceof Attachment ? $file : null;
    }

    private function attachmentId(string $src, int $ticketId): ?int
    {
        $path = parse_url(trim($src), PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $sample = route('laravel-ticket.tickets.attachments.show', [
            'ticket' => $ticketId,
            'attachment' => '0',
        ], false);
        $prefix = substr($sample, 0, -1);

        if ($prefix === '' || ! str_starts_with($path, $prefix)) {
            return null;
        }

        $id = substr($path, strlen($prefix));

        if ($id === '' || ! ctype_digit($id)) {
            return null;
        }

        return (int) $id;
    }

    private function blank(DOMElement $element): bool
    {
        if (in_array($element->nodeName, ['br', 'img'], true)) {
            return false;
        }

        if ($element->getElementsByTagName('img')->length > 0) {
            return false;
        }

        return trim($element->textContent) === '';
    }

    private function serialize(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $html .= htmlspecialchars($child->data, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = $child->nodeName;
            $attributes = '';

            if ($child->hasAttributes()) {
                foreach ($child->attributes as $attribute) {
                    $attributes .= ' '.$attribute->name.'="'.htmlspecialchars($attribute->value, ENT_QUOTES | ENT_HTML5, 'UTF-8').'"';
                }
            }

            if (in_array($tag, ['br', 'img'], true)) {
                $html .= '<'.$tag.$attributes.'>';

                continue;
            }

            $html .= '<'.$tag.$attributes.'>'.$this->serialize($child).'</'.$tag.'>';
        }

        return $html;
    }
}
