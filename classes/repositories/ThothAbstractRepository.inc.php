<?php

use ThothApi\GraphQL\Models\AbstractText as ThothAbstract;

class ThothAbstractRepository
{
    protected $thothClient;

    public function __construct($thothClient)
    {
        $this->thothClient = $thothClient;
    }

    public function new(array $data = [])
    {
        return new ThothAbstract($data);
    }

    public function add($thothAbstract)
    {
        return $this->thothClient->createAbstract($thothAbstract);
    }

    public function edit($thothPatchAbstract)
    {
        return $this->thothClient->updateAbstract($thothPatchAbstract);
    }

    public function delete($thothAbstractId)
    {
        return $this->thothClient->deleteAbstract($thothAbstractId);
    }
}
