<?php
/**
 * To change this template use Tools | Templates.
 */

class Collection {
    // add_entity_relationship
    function create(Entry $relationship) {}

    // get_relationship
    function get($id) {}
    
    // get_entity_relationships
    function find(Query $query) {}
        
    // check_entity_relationship
    function findOne(Query $query) {}
    function count(Query $query) {}
    
    // delete_relationship
    function delete($id) {}

    // remove_entity_relationships
    function remove(Query $query) {}
    
}
