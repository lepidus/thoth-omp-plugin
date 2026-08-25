<?php

namespace APP\plugins\generic\thoth\classes\Domain\Imprint;

final class Imprint
{
    public function __construct(
        private ImprintId $id,
        private string $name
    ) {
    }

    public function id(): ImprintId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }
}
