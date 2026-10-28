<?php

namespace Coderflex\LaravelTicket\Http\Controllers;

use Coderflex\LaravelTicket\Models\Attachment;
use Coderflex\LaravelTicket\Models\Message;
use Coderflex\LaravelTicket\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AttachmentController
{
    public function store(Request $request, int $ticket): JsonResponse
    {
        $actor = $this->actor($request);
        $record = $this->ticket($ticket);
        Gate::forUser($actor)->authorize('view', $record);

        $max = (int) config('laravel_ticket.attachments.max_kilobytes', 10240);
        $request->validate([
            'file' => ['required', 'file', 'max:'.$max, 'mimes:jpg,jpeg,png,gif,webp'],
        ]);

        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            abort(422);
        }

        $attachment = $record->attachFile($file, $actor);
        $attachment->forceFill(['is_inline' => true])->save();

        return response()->json([
            'id' => $attachment->getKey(),
            'url' => route('laravel-ticket.tickets.attachments.show', [$record, $attachment], false),
            'name' => $attachment->original_name,
        ]);
    }

    public function show(Request $request, int $ticket, int $attachment): Response
    {
        $actor = $this->actor($request);
        $record = $this->ticket($ticket);
        Gate::forUser($actor)->authorize('view', $record);

        $file = Attachment::query()
            ->where('ticket_id', $record->getKey())
            ->whereKey($attachment)
            ->first();

        if (! $file instanceof Attachment) {
            abort(404);
        }

        $message = $file->message()->first();

        if ($message instanceof Message && $message->visibility !== 'public') {
            Gate::forUser($actor)->authorize('addInternalNote', $record);
        }

        if (! $message instanceof Message && (int) $file->user_id !== (int) $actor->getKey()) {
            Gate::forUser($actor)->authorize('update', $record);
        }

        $disk = Storage::disk($file->disk);

        if (! $disk->exists($file->path)) {
            abort(404);
        }

        $bytes = $disk->get($file->path);

        if (! is_string($bytes)) {
            abort(404);
        }

        $name = str_replace(['"', "\r", "\n"], '', $file->original_name);
        $inline = is_string($file->mime) && str_starts_with($file->mime, 'image/');

        return response($bytes, 200, [
            'Content-Type' => is_string($file->mime) && $file->mime !== '' ? $file->mime : 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function ticket(int $id): Ticket
    {
        $ticket = Ticket::query()->find($id);

        if (! $ticket instanceof Ticket) {
            abort(404);
        }

        return $ticket;
    }

    private function actor(Request $request): Model
    {
        $user = $request->user();

        if (! $user instanceof Model) {
            abort(403);
        }

        return $user;
    }
}
