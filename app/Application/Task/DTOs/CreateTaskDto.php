<?php

namespace App\Application\Task\DTOs;

class CreateTaskDto
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
        public readonly int $status,
        public readonly ?string $due_date,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            description: $data['description'] ?? null,
            status: $data['status'] ?? 0,
            due_date: $data['due_date'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'due_date' => $this->due_date,
        ];
    }
}
