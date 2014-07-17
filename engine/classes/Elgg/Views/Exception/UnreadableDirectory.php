<?php
namespace Elgg\Views\Exception;

/**
 * @since 2.0.0
 */
class UnreadableDirectory extends \Exception {
    public $path;
    
    /**
     * @param string $path
     */
    public function __construct($path) {
        $this->path = $path;
    }
}