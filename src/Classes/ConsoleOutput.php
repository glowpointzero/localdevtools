<?php
namespace GlowPointZero\LocalDevTools;

class ConsoleOutput extends \Symfony\Component\Console\Output\ConsoleOutput
{
    
    public function writeHeader($headerContent)
    {
        $this->write([
            '===================================',
            $headerContent,
            '==================================='
        ], true);
    }
}