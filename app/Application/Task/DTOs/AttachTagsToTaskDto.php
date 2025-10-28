<?php

namespace App\Application\Task\DTOs;

class AttachTagsToTaskDto
{
    public function __construct(
        public readonly int $taskId,
        public readonly array $tagIds,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            taskId: (int) $data['task_id'],
            tagIds: $data['tag_ids'] ?? [],
        );
    }
}
