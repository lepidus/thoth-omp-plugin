<?php

interface ThothConfigurationRepository
{
    public function get(int $contextId): ThothConfiguration;
}
