<?php

function elgg_solr_add_update_entity($event, $type, $entity) {
	$debug = false;
	if (elgg_get_config('elgg_solr_debug')) {
		$debug = true;
	}
	
	
	if (!elgg_instanceof($entity)) {
		if ($debug) {
			elgg_solr_debug_log('Not a valid elgg entity');
		}
		return true;
	}
	
	if (!is_registered_entity_type($entity->type, $entity->getSubtype())) {
		if ($debug) {
			elgg_solr_debug_log('Not a registered entity type');
		}
		return true;
	}
	
	$function = elgg_solr_get_solr_function($entity->type, $entity->getSubtype());
	
	if (is_callable($function)) {
		if ($debug) {
			elgg_solr_debug_log('processing entity with function - ' . $function);
		}
		
		$function($entity);
	}
	else {
		if ($debug) {
			elgg_solr_debug_log('Not a callable function - ' . $function);
		}
	}
}



function elgg_solr_delete_entity($event, $type, $entity) {
	
	if (!elgg_instanceof($entity)) {
		return true;
	}
	
	if (!is_registered_entity_type($entity->type, $entity->getSubtype())) {
		return true;
	}
	
	elgg_solr_defer_index_delete($entity->guid);

    return true;
}



function elgg_solr_metadata_update($event, $type, $metadata) {
	elgg_solr_defer_index_update($metadata->entity_guid);
}


// reindexes entities by guid
// happens after shutdown thanks to vroom
// entity guids stored in config
function elgg_solr_entities_sync() {
	
	$access = access_get_show_hidden_status();
	access_show_hidden_entities(true);
	$guids = elgg_get_config('elgg_solr_sync');
	
	if (!$guids) {
		return true;
	}
	
	$options = array(
		'guids' => array_keys($guids),
		'limit' => false
	);
	
	$batch_size = elgg_get_plugin_setting('reindex_batch_size', 'elgg_solr');
	$entities = new ElggBatch('elgg_get_entities', $options, null, $batch_size);
	
	foreach ($entities as $e) {
		elgg_solr_add_update_entity(null, null, $e);
	}
	
	$delete_guids = elgg_get_config('elgg_solr_delete');
	
	if (is_array($delete_guids)) {
		foreach ($delete_guids as $g => $foo) {
			$client = elgg_solr_get_client();
			$query = $client->createUpdate();
			$query->addDeleteById($g);
			$query->addCommit();
			$client->update($query);
		}
	}
	
	access_show_hidden_entities($access);
}


function elgg_solr_profile_update($event, $type, $entity) {
	$guids = elgg_get_config('elgg_solr_sync');
	if (!is_array($guids)) {
		$guids = array();
	}
	$guids[$entity->guid] = 1; // use key to keep it unique
	
	elgg_set_config('elgg_solr_sync', $guids);
}


function elgg_solr_upgrades() {
	$ia = elgg_set_ignore_access(true);
	elgg_load_library('elgg_solr:upgrades');
	
	run_function_once('elgg_solr_upgrade_20140504b');
	run_function_once('elgg_solr_upgrade_20141205');
	
	elgg_set_ignore_access($ia);
}

function elgg_solr_disable_entity($event, $type, $entity) {
	elgg_solr_defer_index_update($entity->guid);
}

function elgg_solr_enable_entity($event, $type, $entity) {
	elgg_solr_defer_index_update($entity->guid);
}