<?php

namespace Elgg\Storage\Entities;                                        

use Elgg\Storage\Sites;

/**
 * To change this template use Tools | Templates.
 * 
 * @access private
 */
class Query {
    public function guid($comparison, $value);
    public function subtype($comparison, $value);
    public function site_guid($comparison, $value);
    public function time_created($comparison, DateTime $value);
    public function time_updated($comparison, DateTime $value);
    public function last_action($comparison, DateTime $value);
    public function enabled($comparison, $value = true);
    
    public function site(Sites\Query $query);
    
    public function where($prop, $comparison, $value);
    public function sort(array $options = array());
    public function offset($count);
    public function limit($count);
}