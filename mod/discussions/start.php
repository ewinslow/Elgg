<?php
/**
 * Discussion plugin
 */

elgg_register_event_handler('init', 'system', 'discussion_init');

/**
 * Initialize the discussion component
 */
function discussion_init() {

	elgg_register_library('elgg:discussion', __DIR__ . '/lib/discussion.php');

	elgg_register_page_handler('discussion', 'discussion_page_handler');

	elgg_register_plugin_hook_handler('entity:url', 'object', 'discussion_set_topic_url');

	// commenting not allowed on discussion topics (use a different annotation)
	elgg_register_plugin_hook_handler('permissions_check:comment', 'object', 'discussion_comment_override');
	elgg_register_plugin_hook_handler('permissions_check', 'object', 'discussion_can_edit_reply');

	// discussion reply menu
	elgg_register_plugin_hook_handler('register', 'menu:entity', 'discussion_reply_menu_setup');

	// allow non-owners to add replies to discussion
	elgg_register_plugin_hook_handler('container_permissions_check', 'object', 'discussion_reply_container_permissions_override');

	elgg_register_event_handler('update:after', 'object', 'discussion_update_reply_access_ids');

	$action_base = __DIR__ . '/actions/discussion';
	elgg_register_action('discussion/save', "$action_base/save.php");
	elgg_register_action('discussion/delete', "$action_base/delete.php");
	elgg_register_action('discussion/reply/save', "$action_base/reply/save.php");
	elgg_register_action('discussion/reply/delete', "$action_base/reply/delete.php");

	// add link to owner block
	elgg_register_plugin_hook_handler('register', 'menu:owner_block', 'discussion_owner_block_menu');

	// Register for search.
	elgg_register_entity_type('object', 'discussion');
	elgg_register_plugin_hook_handler('search', 'object:discussion', 'discussion_search_discussion');

	// because replies are not comments, need of our menu item
	elgg_register_plugin_hook_handler('register', 'menu:river', 'discussion_add_to_river_menu');

	// add the forum tool option
	add_group_tool_option('forum', elgg_echo('groups:enableforum'), true);
	elgg_extend_view('groups/tool_latest', 'discussion/group_module');

	elgg_register_js('elgg.discussion', elgg_get_simplecache_url('discussion/discussion.js'));

	elgg_register_ajax_view('ajax/discussion/reply/edit');

	// notifications
	elgg_register_plugin_hook_handler('get', 'subscriptions', 'discussion_get_subscriptions');
	elgg_register_notification_event('object', 'discussion');
	elgg_register_plugin_hook_handler('prepare', 'notification:create:object:discussion', 'discussion_prepare_notification');
	elgg_register_notification_event('object', 'discussion_reply');
	elgg_register_plugin_hook_handler('prepare', 'notification:create:object:discussion_reply', 'discussion_prepare_reply_notification');

	// allow ecml in discussion
	elgg_register_plugin_hook_handler('get_views', 'ecml', 'discussion_ecml_views_hook');

	// allow to be liked
	elgg_register_plugin_hook_handler('likes:is_likable', 'object:discussion', 'Elgg\Values::getTrue');
	elgg_register_plugin_hook_handler('likes:is_likable', 'object:discussion_reply', 'Elgg\Values::getTrue');
}

/**
 * Discussion page handler
 *
 * URLs take the form of
 *  All topics in site:    discussion/all
 *  List topics in forum:  discussion/owner/<guid>
 *  View discussion topic: discussion/view/<guid>
 *  Add discussion topic:  discussion/add/<guid>
 *  Edit discussion topic: discussion/edit/<guid>
 *
 * @param array $page Array of url segments for routing
 * @return bool
 */
function discussion_page_handler($page) {

	elgg_load_library('elgg:discussion');

	if (!isset($page[0])) {
		$page[0] = 'all';
	}

	elgg_push_breadcrumb(elgg_echo('discussion'), 'discussion/all');

	switch ($page[0]) {
		case 'all':
			echo elgg_view_resource('discussion/all');
			break;
		case 'owner':
			set_input('owner_guid', elgg_extract(1, $page));
			echo elgg_view_resource('discussion/owner');
			break;
		case 'add':
			set_input('guid', elgg_extract(1, $page));
			echo elgg_view_resource('discussion/add');
			break;
		case 'reply':
			switch (elgg_extract(1, $page)) {
				case 'edit':
					set_input('guid', elgg_extract(2, $page));
					echo elgg_view_resource('discussion/reply/edit');
					break;
				case 'view':
					discussion_redirect_to_reply(elgg_extract(2, $page), elgg_extract(3, $page));
					break;
				default:
					return false;
			}
			break;
		case 'edit':
			set_input('guid', elgg_extract(1, $page));
			echo elgg_view_resource('discussion/edit');
			break;
		case 'view':
			set_input('guid', elgg_extract(1, $page));
			echo elgg_view_resource('discussion/view');
			break;
		default:
			return false;
	}
	return true;
}

/**
 * Redirect to the reply in context of the containing topic
 *
 * @param int $reply_guid    GUID of the reply
 * @param int $fallback_guid GUID of the topic
 *
 * @return void
 * @access private
 */
function discussion_redirect_to_reply($reply_guid, $fallback_guid) {
	$fail = function () {
		register_error(elgg_echo('discussion:reply:error:notfound'));
		forward(REFERER);
	};

	$reply = get_entity($reply_guid);
	if (!$reply) {
		// try fallback
		$fallback = get_entity($fallback_guid);
		if (!elgg_instanceof($fallback, 'object', 'discussion')) {
			$fail();
		}

		register_error(elgg_echo('discussion:reply:error:notfound_fallback'));
		forward($fallback->getURL());
	}

	if (!$reply instanceof ElggDiscussionReply) {
		$fail();
	}

	// start with topic URL
	$topic = $reply->getContainerEntity();

	// this won't work with threaded comments, but core doesn't support that yet
	$count = elgg_get_entities([
		'type' => 'object',
		'subtype' => $reply->getSubtype(),
		'container_guid' => $topic->guid,
		'count' => true,
		'wheres' => ["e.guid < " . (int)$reply->guid],
	]);
	$limit = (int)get_input('limit', 0);
	if (!$limit) {
		$limit = _elgg_services()->config->get('default_limit');
	}
	$offset = floor($count / $limit) * $limit;
	if (!$offset) {
		$offset = null;
	}

	$url = elgg_http_add_url_query_elements($topic->getURL(), [
			'offset' => $offset,
		]) . "#elgg-object-{$reply->guid}";

	forward($url);
}

/**
 * Override the url for discussion topics and replies
 *
 * Discussion replies do not have their own page so their url is
 * the same as the topic url.
 *
 * @param string $hook
 * @param string $type
 * @param string $url
 * @param array  $params
 * @return string
 */
function discussion_set_topic_url($hook, $type, $url, $params) {
	$entity = $params['entity'];

	if (!$entity instanceof ElggObject) {
		return;
	}

	if ($entity->getSubtype() === 'discussion') {
		$title = elgg_get_friendly_title($entity->title);
		return "discussion/view/{$entity->guid}/{$title}";
	}

	if (!$entity instanceof ElggDiscussionReply) {
		return;
	}

	$topic = $entity->getContainerEntity();

	return "discussion/reply/view/{$entity->guid}/{$topic->guid}";
}

/**
 * We don't want people commenting on topics in the river
 *
 * @param string $hook
 * @param string $type
 * @param string $return
 * @param array  $params
 * @return bool
 */
function discussion_comment_override($hook, $type, $return, $params) {
	if (elgg_instanceof($params['entity'], 'object', 'discussion')) {
		return false;
	}
}

/**
 * Add owner block link for groups
 *
 * @param string         $hook   'register'
 * @param string         $type   'menu:owner_block'
 * @param ElggMenuItem[] $return
 * @param array          $params
 * @return ElggMenuItem[] $return
 */
function discussion_owner_block_menu($hook, $type, $return, $params) {
	if (elgg_instanceof($params['entity'], 'group')) {
		if ($params['entity']->forum_enable != "no") {
			$url = "discussion/owner/{$params['entity']->guid}";
			$item = new ElggMenuItem('discussion', elgg_echo('discussion:group'), $url);
			$return[] = $item;
		}
	}

	return $return;
}

/**
 * Set up menu items for river items
 *
 * Add reply button for discussion topic. Remove the possibility
 * to comment on a discussion reply.
 *
 * @param string         $hook   'register'
 * @param string         $type   'menu:river'
 * @param ElggMenuItem[] $return
 * @param array          $params
 * @return ElggMenuItem[] $return
 */
function discussion_add_to_river_menu($hook, $type, $return, $params) {
	if (!elgg_is_logged_in() || elgg_in_context('widgets')) {
		return $return;
	}

	$item = $params['item'];
	$object = $item->getObjectEntity();

	if (elgg_instanceof($object, 'object', 'discussion')) {
		$container = $object->getContainerEntity();

		if ($container && ($container->canWriteToContainer() || elgg_is_admin_logged_in())) {
				$options = array(
				'name' => 'reply',
				'href' => "#discussion-reply-{$object->guid}",
				'text' => elgg_view_icon('speech-bubble'),
				'title' => elgg_echo('reply:this'),
				'rel' => 'toggle',
				'priority' => 50,
			);
			$return[] = ElggMenuItem::factory($options);
		}
	} else {
		if (elgg_instanceof($object, 'object', 'discussion_reply', 'ElggDiscussionReply')) {
			// Discussion replies cannot be commented
			foreach ($return as $key => $item) {
				if ($item->getName() === 'comment') {
					unset($return[$key]);
				}
			}
		}
	}

	return $return;
}

/**
 * Prepare a notification message about a new discussion topic
 *
 * @param string                          $hook         Hook name
 * @param string                          $type         Hook type
 * @param Elgg\Notifications\Notification $notification The notification to prepare
 * @param array                           $params       Hook parameters
 * @return Elgg\Notifications\Notification
 */
function discussion_prepare_notification($hook, $type, $notification, $params) {
	$entity = $params['event']->getObject();
	$owner = $params['event']->getActor();
	$language = $params['language'];

	$descr = $entity->description;
	$title = $entity->title;

	$notification->subject = elgg_echo('discussion:topic:notify:subject', array($title), $language);
	$notification->body = elgg_echo('discussion:topic:notify:body', array(
		$owner->name,
		$title,
		$descr,
		$entity->getURL()
	), $language);
	$notification->summary = elgg_echo('discussion:topic:notify:summary', array($entity->title), $language);

	return $notification;
}

/**
 * Prepare a notification message about a new discussion reply
 *
 * @param string                          $hook         Hook name
 * @param string                          $type         Hook type
 * @param Elgg\Notifications\Notification $notification The notification to prepare
 * @param array                           $params       Hook parameters
 * @return Elgg\Notifications\Notification
 */
function discussion_prepare_reply_notification($hook, $type, $notification, $params) {
	$reply = $params['event']->getObject();
	$topic = $reply->getContainerEntity();
	$poster = $reply->getOwnerEntity();
	$language = elgg_extract('language', $params);

	$notification->subject = elgg_echo('discussion:reply:notify:subject', array($topic->title), $language);
	$notification->body = elgg_echo('discussion:reply:notify:body', array(
		$poster->name,
		$topic->title,
		$reply->description,
		$reply->getURL(),
	), $language);
	$notification->summary = elgg_echo('discussion:reply:notify:summary', array($topic->title), $language);

	return $notification;
}

/**
 * Get subscriptions for notifications
 *
 * @param string $hook          'get'
 * @param string $type          'subscriptions'
 * @param array  $subscriptions Array containing subscriptions in the form
 *                              <user guid> => array('email', 'site', etc.)
 * @param array  $params        Hook parameters
 * @return array
 */
function discussion_get_subscriptions($hook, $type, $subscriptions, $params) {
	$reply = $params['event']->getObject();

	if (!elgg_instanceof($reply, 'object', 'discussion_reply', 'ElggDiscussionReply')) {
		return $subscriptions;
	}

	$container_guid = $reply->getContainerEntity()->container_guid;
	$container_subscriptions = elgg_get_subscriptions_for_container($container_guid);

	return ($subscriptions + $container_subscriptions);
}

/**
 * Parse ECML on discussion views
 */
function discussion_ecml_views_hook($hook, $type, $return_value, $params) {
	$return_value['forum/viewposts'] = elgg_echo('discussion:ecml:discussion');

	return $return_value;
}


/**
 * Allow group owner and discussion owner to edit discussion replies.
 *
 * @param string  $hook   'permissions_check'
 * @param string  $type   'object'
 * @param boolean $return
 * @param array   $params Array('entity' => ElggEntity, 'user' => ElggUser)
 * @return boolean True if user is discussion or group owner
 */
function discussion_can_edit_reply($hook, $type, $return, $params) {
	/** @var $reply ElggEntity */
	$reply = $params['entity'];
	$user = $params['user'];

	if (!elgg_instanceof($reply, 'object', 'discussion_reply', 'ElggDiscussionReply')) {
		return $return;
	}

	if ($reply->owner_guid == $user->guid) {
	    return true;
	}

	$discussion = $reply->getContainerEntity();
	if ($discussion->owner_guid == $user->guid) {
		return true;
	}

	$container = $discussion->getContainerEntity();
	if (elgg_instanceof($container, 'group') && $container->owner_guid == $user->guid) {
		return true;
	}

	return false;
}

/**
 * Make sure that only group members can post to a group discussion
 *
 * @param string $hook   'container_permissions_check'
 * @param string $type   'object'
 * @param array  $return
 * @param array  $params Array with container, user and subtype
 * @return boolean $return
 */
function discussion_reply_container_permissions_override($hook, $type, $return, $params) {
	if ($params['subtype'] !== 'discussion_reply') {
		return $return;
	}

	/** @var $discussion ElggEntity */
	$discussion = $params['container'];
	$user = $params['user'];

	$container = $discussion->getContainerEntity();

	if (elgg_instanceof($container, 'group')) {
		// Only group members are allowed to reply
		// to a discussion within a group
		if (!$container->canWriteToContainer($user->guid)) {
			return false;
		}
	}

	return true;
}

/**
 * Update access_id of discussion replies when topic access_id is updated.
 *
 * @param string     $event  'update'
 * @param string     $type   'object'
 * @param ElggObject $object ElggObject
 */
function discussion_update_reply_access_ids($event, $type, $object) {
	if (!elgg_instanceof($object, 'object', 'discussion')) {
		return;
	}

	$ia = elgg_set_ignore_access(true);
	$options = array(
		'type' => 'object',
		'subtype' => 'discussion_reply',
		'container_guid' => $object->getGUID(),
		'limit' => 0,
	);
	$batch = new ElggBatch('elgg_get_entities', $options);
	foreach ($batch as $reply) {
		if ($reply->access_id == $object->access_id) {
			// Assume access_id of the replies is up-to-date
			break;
		}

		// Update reply access_id
		$reply->access_id = $object->access_id;
		$reply->save();
	}

	elgg_set_ignore_access($ia);
}

/**
 * Set up discussion reply entity menu
 *
 * @param string          $hook   'register'
 * @param string          $type   'menu:entity'
 * @param ElggMenuItem[]  $return
 * @param array           $params
 * @return ElggMenuItem[] $return
 */
function discussion_reply_menu_setup($hook, $type, $return, $params) {
	/** @var $reply ElggEntity */
	$reply = elgg_extract('entity', $params);

	if (empty($reply) || !elgg_instanceof($reply, 'object', 'discussion_reply')) {
		return $return;
	}

	if (!elgg_is_logged_in()) {
		return $return;
	}

	if (elgg_in_context('widgets')) {
		return $return;
	}

	// Reply has the same access as the topic so no need to view it
	$remove = array('access');

	$user = elgg_get_logged_in_user_entity();

	// Allow discussion topic owner, group owner and admins to edit and delete
	if ($reply->canEdit() && !elgg_in_context('activity')) {
		$return[] = ElggMenuItem::factory(array(
			'name' => 'edit',
			'text' => elgg_echo('edit'),
			'href' => "discussion/reply/edit/{$reply->guid}",
			'priority' => 150,
		));

		$return[] = ElggMenuItem::factory(array(
			'name' => 'delete',
			'text' => elgg_view_icon('delete'),
			'href' => "action/discussion/reply/delete?guid={$reply->guid}",
			'priority' => 150,
			'is_action' => true,
			'confirm' => elgg_echo('deleteconfirm'),
		));
	} else {
		// Edit and delete links can be removed from all other users
		$remove[] = 'edit';
		$remove[] = 'delete';
	}

	// Remove unneeded menu items
	foreach ($return as $key => $item) {
		if (in_array($item->getName(), $remove)) {
			unset($return[$key]);
		}
	}

	return $return;
}

/**
 * Search in both discussion topics and replies
 *
 * @param string $hook   the name of the hook
 * @param string $type   the type of the hook
 * @param mixed  $value  the current return value
 * @param array  $params supplied params
 */
function discussion_search_discussion($hook, $type, $value, $params) {

	if (empty($params) || !is_array($params)) {
		return $value;
	}

	$subtype = elgg_extract("subtype", $params);
	if (empty($subtype) || ($subtype !== "discussion")) {
		return $value;
	}

	unset($params["subtype"]);
	$params["subtypes"] = array("discussion", "discussion_reply");

	// trigger the 'normal' object search as it can handle the added options
	return elgg_trigger_plugin_hook('search', 'object', $params, array());
}
