<?php
namespace GlowPointZero\LocalDevTools\Command;

interface SetupCommandInterface
{
    public function getConfigurationTitle();
    public function getConfiguredValues();
}
