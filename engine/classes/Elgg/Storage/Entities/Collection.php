<?php
namespace Elgg\Storage\Entities;

/**
 * 
 * 
 * @access private
 */
final class Collection {
    public function find(Query $query) {}
    public function findOne(Query $query) {}
    public function count(Query $query) {}

    public function insertOne(Entry $entity) {}
    public function updateOne(Entry $entity) {}
    public function removeOne(Entry $entity) {}
}