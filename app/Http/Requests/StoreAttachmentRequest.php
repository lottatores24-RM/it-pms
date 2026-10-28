<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesCollaborationTarget;
use App\Models\Attachment;
use App\Models\Comment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttachmentRequest extends FormRequest
{
    use ResolvesCollaborationTarget;

    /**
     * Kept apart from the comment form on the same page.
     *
     * @var string
     */
    protected $errorBag = 'attachment';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Attachment::class, $this->target()]);
    }

    /**
     * PDF, DOC/DOCX, XLS/XLSX, PNG, JPG and ZIP up to 10 MB (FR-018). Both the
     * detected content type (mimes) and the file name extension are checked.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $extensions = implode(',', Attachment::ALLOWED_EXTENSIONS);

        return [
            'attachable_type' => ['required', Rule::in(array_keys(Comment::TARGET_TYPES))],
            'attachable_id' => ['required', 'integer'],
            'file' => [
                'required',
                'file',
                'max:'.Attachment::MAX_KILOBYTES,
                "mimes:{$extensions}",
                "extensions:{$extensions}",
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $allowed = 'PDF, DOC, DOCX, XLS, XLSX, PNG, JPG, atau ZIP';

        return [
            'file.required' => 'Pilih file yang akan diunggah.',
            'file.uploaded' => 'File gagal diunggah. Pastikan ukurannya tidak lebih dari 10 MB.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
            'file.mimes' => "Format file tidak didukung. Gunakan {$allowed}.",
            'file.extensions' => "Format file tidak didukung. Gunakan {$allowed}.",
        ];
    }

    protected function targetTypeKey(): string
    {
        return 'attachable_type';
    }

    protected function targetIdKey(): string
    {
        return 'attachable_id';
    }
}
