--
-- Elgg database schema
--
-- application specific configuration
CREATE TABLE `prefix_config` (
  `name` varchar(255) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8;

-- primary content table
CREATE TABLE `prefix_entities` (
  `guid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type_id` int(11) NOT NULL,
  `creator_guid` bigint(20) unsigned,
  `site_guid` bigint(20) unsigned,
  `time_drafted` TIMESTAMP NOT NULL,
  `time_published` TIMESTAMP,
  `time_updated` TIMESTAMP,
  `time_deleted` TIMESTAMP,
  `status` enum('draft', 'published', 'deleted') DEFAULT 'draft',
  `enabled` boolean NOT NULL DEFAULT TRUE,
  `alias` varchar(128) DEFAULT NULL,
  `name` text DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `url` varchar(255),
  PRIMARY KEY (`guid`),
  KEY `type` (`type`),
  KEY `owner_guid` (`owner_guid`),
  KEY `site_guid` (`site_guid`),
  KEY `container_guid` (`container_guid`),
  KEY `time_drafted` (`time_drafted`),
  KEY `time_published` (`time_published`),
  KEY `time_updated` (`time_updated`),
  KEY `time_deleted` (`time_deleted`),
  UNIQUE KEY `alias` (`site_guid`, `subtype`, `alias`),
  UNIQUE KEY `url` (`url`),
  FULLTEXT KEY `text` (`name`, `summary`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

-- TODO: Add foreign key from subtype to entity_subtypes(id)
-- TODO: Add foreign key from owner_guid to entities(guid)
-- TODO: Add foreign key from site_guid to entities(guid)
-- TODO: Add foreign key from container_guid to entities(guid)

-- custom properties for entities
CREATE TABLE `prefix_metadata_names` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `name` (`name`(50))
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

-- metadata that describes an entity
CREATE TABLE `prefix_metadata` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_guid` bigint(20) unsigned NOT NULL,
  `name_id` int(11) unsigned NOT NULL,
  `value_int` bigint(20),
  `value_double` double precision,
  `value_text` text,
  `private` boolean NOT NULL default FALSE,
  PRIMARY KEY (`id`),
  KEY `entity_guid` (`entity_guid`),
  KEY `name_id` (`entity_guid`,`name_id`),
  KEY `owner_guid` (`owner_guid`),
  KEY `access_id` (`access_id`),
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

-- TODO: Add foreign key from entity_guid to entities(guid)
-- TODO: Add foreign key from name_id to metastrings(id)
-- TODO: Add foreign key from owner_guid to entities(guid)

-- lookup table mapping list names to ints
CREATE TABLE `prefix_list_names` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`name`),
) ENGINE=InnoDB DEFAULT CHARSET=utf8

-- connect entities to other entities
CREATE TABLE `prefix_lists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `entity_guid` bigint(20) unsigned NOT NULL,
  `name_id` int(11) unsigned NOT NULL,
  `count` int(11) unsigned NOT NULL, -- total number of items in the list
  `sum` bigint(20) NOT NULL, -- computed sum of all items' weights
  PRIMARY KEY (`id`),
  KEY `entity_guid` (`entity_guid`),
  UNIQUE KEY `list` (`entity_guid`,`name_id`),
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- TODO: Add foreign key constraint from entity_guid to entities table
-- TODO: Add foreign key constraint from name to list_names table

-- lists have other lists as members
-- There is a special list named "__self__" that terminates the recursion.
-- The "__self__" list can only be a sublist, it cannot contain sublists.
CREATE TABLE `prefix_sublists` (
  `list_id` bigint(20) unsigned NOT NULL,
  `sublist_id` bigint(20) unsigned NOT NULL,
  `weight` double precision NOT NULL, -- TODO: should this be a big int instead?
  `time_created` TIMESTAMP NOT NULL,
  `time_updated` TIMESTAMP NOT NULL,
  PRIMARY KEY (`list_id`, `sublist_id`),
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- TODO: Add foreign key constraint from list_id to lists table
-- TODO: Add foreign key constraint from item_id to lists table


-- queue for asynchronous operations
CREATE TABLE `prefix_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `data` mediumblob NOT NULL,
  `timestamp` int(11) NOT NULL,
  `worker` varchar(32) NULL,
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `retrieve` (`timestamp`,`worker`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- log activity for the admin
CREATE TABLE `prefix_system_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `object_id` int(11) NOT NULL,
  `object_class` varchar(50) NOT NULL,
  `object_type` varchar(50) NOT NULL,
  `object_subtype` varchar(50) NOT NULL,
  `event` varchar(50) NOT NULL,
  `performed_by_guid` int(11) NOT NULL,
  `owner_guid` int(11) NOT NULL,
  `is_enabled` boolean NOT NULL DEFAULT TRUE,
  `time_created` TIMESTAMP NOT NULL,
  `ip_address` varchar(46) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `object_class` (`object_class`),
  KEY `object_type` (`object_type`),
  KEY `object_subtype` (`object_subtype`),
  KEY `event` (`event`),
  KEY `performed_by_guid` (`performed_by_guid`),
  KEY `access_id` (`access_id`),
  KEY `time_created` (`time_created`),
  KEY `river_key` (`object_type`,`object_subtype`,`event`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
