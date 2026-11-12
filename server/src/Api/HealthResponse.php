<?php

namespace Api;

class HealthResponse implements \JsonSerializable {

    public function __construct(
        public string $status = 'ok',
    ) {}

    public function jsonSerialize(): array {
        return ['status' => $this->status];
    }
}
