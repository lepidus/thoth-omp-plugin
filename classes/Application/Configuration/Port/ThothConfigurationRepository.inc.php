<?php

interface ThothConfigurationRepository
{
    public function get(int $contextId): ThothConfiguration;

    public function save(int $contextId, ThothConfiguration $configuration): void;
}
