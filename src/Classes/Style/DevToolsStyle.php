<?php
namespace GlowPointZero\LocalDevTools\Style;

class DevToolsStyle extends \Symfony\Component\Console\Style\SymfonyStyle
{
    
    /**
     * Outputs a 'processing... ' message
     * 
     * @param string $message
     */
    public function processing($message)
    {
        $lastCharacter = substr($message, -1);
        if ( (!in_array($lastCharacter, ['.', '!', ' ', '?'])) ) {
            $message .= '... ';
        }
        $this->write(' '. $message);
    }
    
    
    /**
     * Writes a 'ok' message to the output, inline per default
     * 
     * @param type $message
     * @param type $inline
     * @param type $appendNewLine
     */
    public function ok($message = 'ok', $inline = true, $appendNewLine = true)
    {
        $this->write(
            sprintf(
                '<info>%s%s</info>',
                $inline ? '' : ' ',
                $message
            ),
            $appendNewLine
        );
    }
}