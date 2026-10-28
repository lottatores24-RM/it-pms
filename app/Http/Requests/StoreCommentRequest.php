<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesCollaborationTarget;
use App\Models\Comment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    use ResolvesCollaborationTarget;

    /**
     * Kept apart from the attachment form on the same page.
     *
     * @var string
     */
    protected $errorBag = 'comment';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Comment::class, $this->target()]);
    }

    /**
     * Get the validation rules that apply to the request (FR-018: 1–2000 characters).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'commentable_type' => ['required', Rule::in(array_keys(Comment::TARGET_TYPES))],
            'commentable_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['body' => 'komentar'];
    }

    protected function targetTypeKey(): string
    {
        return 'commentable_type';
    }

    protected function targetIdKey(): string
    {
        return 'commentable_id';
    }
}
