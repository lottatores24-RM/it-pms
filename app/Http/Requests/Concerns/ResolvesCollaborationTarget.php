<?php

namespace App\Http\Requests\Concerns;

use App\Models\Comment;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the project, task or issue a comment or attachment is posted to.
 * The target must belong to the project in the URL, otherwise the request
 * ends with 404, so nobody can write into another project's items.
 */
trait ResolvesCollaborationTarget
{
    private ?Model $resolvedTarget = null;

    public function target(): Model
    {
        if ($this->resolvedTarget !== null) {
            return $this->resolvedTarget;
        }

        /** @var Project $project */
        $project = $this->route('project');
        $class = Comment::TARGET_TYPES[(string) $this->input($this->targetTypeKey())] ?? null;
        $id = $this->integer($this->targetIdKey());

        abort_if($class === null, 404);

        if ($class === Project::class) {
            abort_unless($id === $project->id, 404);

            return $this->resolvedTarget = $project;
        }

        $target = $class::query()->where('project_id', $project->id)->find($id);
        abort_if($target === null, 404);

        return $this->resolvedTarget = $target->setRelation('project', $project);
    }

    abstract protected function targetTypeKey(): string;

    abstract protected function targetIdKey(): string;
}
