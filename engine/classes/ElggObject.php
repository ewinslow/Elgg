<?php
/**
 * Elgg Object
 *
 * Elgg objects are the most common means of storing information in the database.
 * They are a child class of \ElggEntity, so receive all the benefits of the Entities,
 * but also include a title and description field.
 *
 * An \ElggObject represents a row from the objects_entity table, as well
 * as the related row in the entities table as represented by the parent
 * \ElggEntity object.
 *
 * @note Internal: Title and description are stored in the objects_entity table.
 *
 * @package    Elgg.Core
 * @subpackage DataModel.Object
 *
 * @property string $title       The title, name, or summary of this object
 * @property string $description The body, description, or content of the object
 * @property array  $tags        Tags that describe the object (metadata)
 */
class ElggObject extends \ElggEntity {

	/**
	 * Initialize the attributes array to include the type,
	 * title, and description.
	 *
	 * @return void
	 */
	protected function initializeAttributes() {
		parent::initializeAttributes();

		$this->attributes['type'] = "object";
		$this->attributes += self::getExternalAttributes();
		$this->tables_split = 2;
	}

	/**
	 * Get default values for attributes stored in a separate table
	 *
	 * @return array
	 * @access private
	 *
	 * @see \Elgg\Database\EntityTable::getEntities
	 */
	final public static function getExternalAttributes() {
		return [
			'title' => null,
			'description' => null,
		];
	}

	/**
	 * Create a new \ElggObject.
	 *
	 * Plugin developers should only use the constructor to create a new entity.
	 * To retrieve entities, use get_entity() and the elgg_get_entities* functions.
	 *
	 * If no arguments are passed, it creates a new entity.
	 * If a database result is passed as a \stdClass instance, it instantiates
	 * that entity.
	 *
	 * @param \stdClass $row Database row result. Default is null to create a new object.
	 *
	 * @throws IOException If cannot load remaining data from db
	 * @throws InvalidParameterException If not passed a db row result
	 */
	public function __construct($row = null) {
		$this->initializeAttributes();

		if (!empty($row)) {
			// Is $row is a DB row from the entity table
			if ($row instanceof \stdClass) {
				// Load the rest
				if (!$this->load($row)) {
					$msg = "Failed to load new " . get_class() . " for GUID: " . $row->guid;
					throw new \IOException($msg);
				}
			} else if (is_numeric($row)) {
				// $row is a GUID so load
				elgg_deprecated_notice('Passing a GUID to constructor is deprecated. Use get_entity()', 1.9);
				if (!$this->load($row)) {
					throw new \IOException("Failed to load new " . get_class() . " from GUID:" . $row);
				}
			} else {
				throw new \InvalidParameterException("Unrecognized value passed to constuctor.");
			}
		}
	}

	/**
	 * Loads the full \ElggObject when given a guid.
	 *
	 * @param mixed $guid GUID of an \ElggObject or the \stdClass object from entities table
	 *
	 * @return bool
	 * @throws InvalidClassException
	 */
	protected function load($guid) {
		$attr_loader = new \Elgg\AttributeLoader(get_class(), 'object', $this->attributes);
		$attr_loader->requires_access_control = !($this instanceof \ElggPlugin);
		$attr_loader->secondary_loader = 'get_object_entity_as_row';

		$attrs = $attr_loader->getRequiredAttributes($guid);
		if (!$attrs) {
			return false;
		}

		$this->attributes = $attrs;
		$this->tables_loaded = 2;
		$this->loadAdditionalSelectValues($attr_loader->getAdditionalSelectValues());
		_elgg_cache_entity($this);

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function create() {
		global $CONFIG;

		$guid = parent::create();
		if (!$guid) {
			// @todo this probably means permission to create entity was denied
			// Is returning false the correct thing to do
			return false;
		}
		$title = sanitize_string($this->title);
		$description = sanitize_string($this->description);
		
		$query = "INSERT into {$CONFIG->dbprefix}objects_entity
			(guid, title, description) values ($guid, '$title', '$description')";

		$result = $this->getDatabase()->insertData($query);
		if ($result === false) {
			// TODO(evan): Throw an exception here?
			return false;
		}
		
		return $guid;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function update() {
		global $CONFIG;

		if (!parent::update()) {
			return false;
		}
		
		$guid = (int)$this->guid;
		$title = sanitize_string($this->title);
		$description = sanitize_string($this->description);

		$query = "UPDATE {$CONFIG->dbprefix}objects_entity
			set title='$title', description='$description' where guid=$guid";

		return $this->getDatabase()->updateData($query) !== false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getDisplayName() {
		return $this->title;
	}

	/**
	 * {@inheritdoc}
	 */
	public function setDisplayName($displayName) {
		$this->title = $displayName;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function prepareObject($object) {
		$object = parent::prepareObject($object);
		$object->title = $this->getDisplayName();
		$object->description = $this->description;
		$object->tags = $this->tags ? $this->tags : array();
		return $object;
	}

	/*
	 * EXPORTABLE INTERFACE
	 */

	/**
	 * Return an array of fields which can be exported.
	 *
	 * @return array
	 * @deprecated 1.9 Use toObject()
	 */
	public function getExportableValues() {
		return array_merge(parent::getExportableValues(), array(
			'title',
			'description',
		));
	}

	/**
	 * Can a user comment on this object?
	 *
	 * @see \ElggEntity::canComment()
	 *
	 * @param int $user_guid User guid (default is logged in user)
	 * @return bool
	 * @since 1.8.0
	 */
	public function canComment($user_guid = 0) {
		$result = parent::canComment($user_guid);
		if ($result !== null) {
			return $result;
		}

		if ($user_guid == 0) {
			$user_guid = _elgg_services()->session->getLoggedInUserGuid();
		}

		// must be logged in to comment
		if (!$user_guid) {
			return false;
		}

		// must be member of group
		if (elgg_instanceof($this->getContainerEntity(), 'group')) {
			if (!$this->getContainerEntity()->canWriteToContainer($user_guid)) {
				return false;
			}
		}

		// no checks on read access since a user cannot see entities outside his access
		return true;
	}
}
