<?php

namespace Coderflex\LaravelTicket\RichText;

use Coderflex\LaravelTicket\Mail\TicketMailer;
use Coderflex\LaravelTicket\Models\Message;
use Coderflex\LaravelTicket\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class MessageWriter
{
    public function __construct(private HtmlBody $html) {}

    public function write(Ticket $ticket, Model $actor, Request $request, string $visibility): Message
    {
        $max = (int) config('laravel_ticket.attachments.max_kilobytes', 10240);
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:50000'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:'.$max, 'mimes:jpg,jpeg,png,gif,webp,pdf,txt,csv,zip,doc,docx,xls,xlsx'],
        ]);

        $clean = $this->html->clean((string) ($data['message'] ?? ''), (int) $ticket->getKey());
        $files = $this->files($request);

        if ($this->html->isEmpty($clean) && $files === []) {
            throw ValidationException::withMessages([
                'message' => 'Write a reply, or attach a file, before sending.',
            ]);
        }

        $plain = $this->html->asPlain($clean);
        $format = $plain === null && $clean !== '' ? 'html' : 'plain';
        $stored = $plain ?? ($format === 'html' ? $clean : $this->html->toText($clean));

        Message::$holdMail = true;

        try {
            $created = $visibility === 'public'
                ? $ticket->messageAsUser($actor, $stored, $format)
                : $ticket->note($stored, $actor, $format);
        } finally {
            Message::$holdMail = false;
        }

        if (! $created instanceof Message) {
            throw ValidationException::withMessages([
                'message' => 'The reply could not be saved.',
            ]);
        }

        if ($format === 'html') {
            $this->html->claim($ticket, $this->html->imageIds($clean, (int) $ticket->getKey()), (int) $created->getKey());
        }

        foreach ($files as $file) {
            $ticket->attachFile($file, $actor, $created);
        }

        if ($visibility === 'public') {
            app(TicketMailer::class)->deliver($ticket, $created->load('attachments'));
        }

        return $created;
    }

    /**
     * @return list<UploadedFile>
     */
    private function files(Request $request): array
    {
        $uploaded = $request->file('files', []);

        if ($uploaded instanceof UploadedFile) {
            return [$uploaded];
        }

        return array_values($uploaded);
    }
}
