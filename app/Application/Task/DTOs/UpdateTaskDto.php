<?php

namespace App\Application\Task\DTOs;

class UpdateTaskDto
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly ?int $status,
        public readonly ?string $due_date,
        public readonly ?int $updated_by = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            title: $data['title'] ?? null,
            description: $data['description'] ?? null,
            status: isset($data['status']) ? (int) $data['status'] : null,
            due_date: $data['due_date'] ?? null,
            updated_by: $data['updated_by'] ?? null,
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->title !== null) {
            $data['title'] = $this->title;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->status !== null) {
            $data['status'] = $this->status;
        }

        if ($this->due_date !== null) {
            $data['due_date'] = $this->due_date;
        }

        if ($this->updated_by !== null) {
            $data['updated_by'] = $this->updated_by;
        }

        return $data;
    }
}
