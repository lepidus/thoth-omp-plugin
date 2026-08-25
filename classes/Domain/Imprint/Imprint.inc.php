<?php

final class Imprint
{
    private ImprintId $id;
    private string $name;
    public function __construct(
        ImprintId $id,
        string $name
    ) {
        $this->id = $id;
        $this->name = $name;
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
