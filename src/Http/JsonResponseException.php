<?php

declare(strict_types=1);

final class JsonResponseException extends RuntimeException
{
    public function __construct(
        public readonly mixed $data,
        public readonly int $status,
    ) {
        parent::__construct(is_array($data) && isset($data['error'])
            ? (string) $data['error']
            : 'JSON response');
    }
}
