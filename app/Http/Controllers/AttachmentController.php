<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Http\Requests\StoreAttachmentRequest;
use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * File attachments on projects, tasks and issues (FR-018). Files live on the
 * private "attachments" disk and are only streamed after authorization.
 */
class AttachmentController extends Controller
{
    public function store(StoreAttachmentRequest $request, Project $project): RedirectResponse
    {
        $target = $request->target();
        $file = $request->file('file');

        $path = $file->store((string) $project->id, Attachment::DISK);

        try {
            $attachment = DB::transaction(function () use ($request, $target, $file, $path): Attachment {
                $attachment = $target->attachments()->create([
                    'user_id' => $request->user()->id,
                    'filename' => basename($path),
                    'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                    'mime_type' => mb_substr((string) $file->getMimeType(), 0, 100),
                    'size' => $file->getSize(),
                    'path' => $path,
                ]);

                ActivityLog::record(ActivityAction::Create, $attachment, 'Mengunggah lampiran', newValues: [
                    'file' => $attachment->original_name,
                    'size' => $attachment->humanSize(),
                ]);

                return $attachment;
            });
        } catch (Throwable $exception) {
            Storage::disk(Attachment::DISK)->delete($path);

            throw $exception;
        }

        return redirect()->to($target->collaborationUrl().'#lampiran')->with('success', "Lampiran \"{$attachment->original_name}\" diunggah.");
    }

    /**
     * Stream the file as a download, never inline, under its original name.
     */
    public function download(Project $project, Attachment $attachment): StreamedResponse
    {
        abort_unless($attachment->project()->is($project), 404);
        Gate::authorize('download', $attachment);

        abort_unless(Storage::disk(Attachment::DISK)->exists($attachment->path), 404);

        return Storage::disk(Attachment::DISK)->download($attachment->path, $attachment->original_name);
    }

    /**
     * Soft delete. The file stays on disk so the audit trail remains complete.
     */
    public function destroy(Project $project, Attachment $attachment): RedirectResponse
    {
        abort_unless($attachment->project()->is($project), 404);
        Gate::authorize('delete', $attachment);

        DB::transaction(function () use ($attachment): void {
            ActivityLog::record(ActivityAction::Delete, $attachment, 'Menghapus lampiran', ['file' => $attachment->original_name]);
            $attachment->delete();
        });

        return redirect()->to($attachment->attachable->collaborationUrl().'#lampiran')->with('success', 'Lampiran dihapus.');
    }
}
