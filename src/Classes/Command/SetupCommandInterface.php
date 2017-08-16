<?php
namespace Glowpointzero\LocalDevTools\Command;

interface SetupCommandInterface
{
    public function getConfigurationTitle();
    public function getConfiguredValues();
}
