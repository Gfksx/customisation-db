<?php
/**
 *
 * @package Titania
 * @version $Id$
 * @copyright (c) 2008 phpBB Customisation Database Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

/**
* @ignore
*/
define('IN_TITANIA', true);
if (!defined('TITANIA_ROOT')) define('TITANIA_ROOT', './');
if (!defined('PHP_EXT')) define('PHP_EXT', substr(strrchr(__FILE__, '.'), 1));
require TITANIA_ROOT . 'common.' . PHP_EXT;

$action = request_var('action', '');

switch ($action)
{
	/**
	* Rate something & remove a rating from something
	*/
	case 'rate' :
		$type = request_var('type', '');
		$id = request_var('id', 0);
		$value = request_var('value', -1.0);

		switch ($type)
		{
			case 'author' :
				$object = new titania_author();
				$object->load($id);
				$object->get_rating();
				$redirect = $object->get_url();

				if (!$object || !$object->author_id)
				{
					trigger_error('AUTHOR_NOT_FOUND');
				}
			break;

			case 'contrib' :
				$object = new titania_contribution();
				$object->load($id);
				$object->get_rating();
				$redirect = $object->get_url();

				if (!$object)
				{
					trigger_error('CONTRIB_NOT_FOUND');
				}
			break;

			default :
				trigger_error('BAD_RATING');
			break;
		}

		$result = ($value == -1) ? $object->rating->delete_rating() : $object->rating->add_rating($value);
		if ($result)
		{
			redirect($redirect);
		}
		else
		{
			trigger_error('BAD_RATING');
		}
	break;

	/**
	* Rerun the MPV or Automod test for the queue
	*/
	case 'mpv' :
	case 'automod' :
		$revision_id = request_var('revision', 0);
		titania::add_lang('contributions');

		// Get the revision, contribution, attachment, and queue
		$revision = new titania_revision(false, $revision_id);
		if (!$revision->load())
		{
			trigger_error('NO_REVISION');
		}
		$contrib = new titania_contribution();
		if (!$contrib->load($revision->contrib_id))
		{
			trigger_error('CONTRIB_NOT_FOUND');
		}
		$revision->contrib = $contrib;
		if (!titania_types::$types[$contrib->contrib_type]->acl_get('view'))
		{
			titania::needs_auth();
		}
		$revision_attachment = new titania_attachment(TITANIA_CONTRIB);
		$revision_attachment->attachment_id = $revision->attachment_id;
		if (!$revision_attachment->load())
		{
			trigger_error('ERROR_NO_ATTACHMENT');
		}
		$queue = $revision->get_queue();

		$zip_file = titania::$config->upload_path . '/' . utf8_basename($revision_attachment->attachment_directory) . '/' . utf8_basename($revision_attachment->physical_filename);
		$download_package = titania_url::build_url('download', array('id' => $revision_attachment->attachment_id));

		if ($action == 'mpv')
		{
			// Start up the machine
			$contrib_tools = new titania_contrib_tools($zip_file);

			// Run MPV
			$mpv_results = $contrib_tools->mpv($download_package);

			if ($mpv_results === false)
			{
				// Too lazy to write another one...teams only anyways
				trigger_error('MPV_TEST_FAILED');
			}
			else
			{
				$uid = $bitfield = $flags = false;
				generate_text_for_storage($mpv_results, $uid, $bitfield, $flags, true, true, true);

				// Add the MPV Results to the queue
				$queue->mpv_results = $mpv_results;
				$queue->mpv_results_bitfield = $bitfield;
				$queue->mpv_results_uid = $uid;
				$queue->submit();
			}
		}
		else if ($action == 'automod')
		{
			$new_dir_name = $contrib->contrib_name_clean . '_' . preg_replace('#[^0-9a-z]#', '_', strtolower($revision->revision_version));

			// Start up the machine
			$contrib_tools = new titania_contrib_tools($zip_file, $new_dir_name);

			// Automod testing time
			$details = '';
			$html_results = $bbcode_results = array();
			$sql = 'SELECT row_id, phpbb_version_branch, phpbb_version_revision FROM ' . TITANIA_REVISIONS_PHPBB_TABLE . '
				WHERE revision_id = ' . $revision->revision_id;
			$result = phpbb::$db->sql_query($sql);
			while ($row = phpbb::$db->sql_fetchrow($result))
			{
				$version_string = $row['phpbb_version_branch'][0] . '.' . $row['phpbb_version_branch'][1] . '.' .$row['phpbb_version_revision'];
				$phpbb_path = $contrib_tools->automod_phpbb_files($version_string);

				if ($phpbb_path === false)
				{
					continue;
				}

				phpbb::$template->assign_vars(array(
					'PHPBB_VERSION'		=> $version_string,
					'TEST_ID'			=> $row['row_id'],
				));

				$html_result = $bbcode_result = '';
				$contrib_tools->automod($phpbb_path, $details, $html_result, $bbcode_result);

				$bbcode_results[] = $bbcode_result;
			}
			phpbb::$db->sql_freeresult($result);

			$bbcode_results = implode("\n\n", $bbcode_results);

			// Update the queue with the results
			$queue = $revision->get_queue();
			$queue->automod_results = $bbcode_results;
			$queue->submit();

			$contrib_tools->remove_temp_files();
		}

		if (sizeof($contrib_tools->error))
		{
			trigger_error(implode('<br />', $contrib_tools->error));
		}

		redirect(titania_url::build_url('manage/queue', array('queue' => titania_types::$types[$queue->queue_type]->url, 'q' => $queue->queue_id)));
	break;

	/**
	* Display all support topics
	*/
	case 'support' :
		// Mark all topics read
		if (request_var('mark', '') == 'topics')
		{
			titania_tracking::track(TITANIA_SUPPORT, 0);
		}

		// Generate the main breadcrumbs
		titania::generate_breadcrumbs(array(
			'ALL_SUPPORT'	=> titania_url::build_url('support/all/'),
		));

		// Mark all topics read
		phpbb::$template->assign_var('U_MARK_TOPICS', titania_url::append_url(titania_url::build_url('support/all/'), array('mark' => 'topics')));

		topics_overlord::display_forums_complete('all_support');

		titania::page_header('CUSTOMISATION_DATABASE');
		titania::page_footer(true, 'all_support.html');
	break;

	/**
	* Display all contributions
	*/
	case 'contributions' :
		// Mark all contribs read
		if (request_var('mark', '') == 'contribs')
		{
			titania_tracking::track(TITANIA_CONTRIB, 0);
		}
		phpbb::$template->assign_vars(array(
			'U_MARK_TOPICS'			=> titania_url::append_url(titania_url::$current_page_url, array('mark' => 'contribs')),
			'L_MARK_TOPICS_READ'	=> phpbb::$user->lang['MARK_CONTRIBS_READ'],
		));

		// Setup the sort tool
		$sort = new titania_sort();
		$sort->set_sort_keys(contribs_overlord::$sort_by);
		$sort->default_sort_key = 't';
		$sort->default_sort_dir = 'd';

		contribs_overlord::display_contribs('all', false, $sort);

		titania::page_header('CUSTOMISATION_DATABASE');
		titania::page_footer(true, 'all_contributions.html');
	break;

	/**
	* Default (display category/contrib list)
	*/
	default :
		titania::_include('functions_display', 'titania_display_categories');

		// Get the category_id
		$category = request_var('c', '');
		$category_ary = explode('-', $category);
		if ($category_ary)
		{
			$category_id = array_pop($category_ary);
		}
		else
		{
			$category_id = (int) $category;
		}

		titania_display_categories($category_id);

		$categories_ary = false;
		if ($category_id != 0)
		{
			// Breadcrumbs
			$category_object = new titania_category;
			$categories_ary = titania::$cache->get_categories();

			// Parents
			foreach (array_reverse(titania::$cache->get_category_parents($category_id)) as $row)
			{
				$category_object->__set_array($categories_ary[$row['category_id']]);
				titania::generate_breadcrumbs(array(
					((isset(phpbb::$user->lang[$categories_ary[$row['category_id']]['category_name']])) ? phpbb::$user->lang[$categories_ary[$row['category_id']]['category_name']] : $categories_ary[$row['category_id']]['category_name'])	=> titania_url::build_url($category_object->get_url()),
				));
			}

			// Self
			$category_object->__set_array($categories_ary[$category_id]);
			titania::generate_breadcrumbs(array(
				((isset(phpbb::$user->lang[$categories_ary[$category_id]['category_name']])) ? phpbb::$user->lang[$categories_ary[$category_id]['category_name']] : $categories_ary[$category_id]['category_name'])	=> titania_url::build_url($category_object->get_url()),
			));

			// Get the child categories we want to select the contributions from
			$child_categories = array_keys(titania::$cache->get_category_children($category_id));

			// Setup the sort tool to sort by update time descending by default
			$sort = new titania_sort();
			$sort->set_sort_keys(contribs_overlord::$sort_by);
			$sort->default_sort_key = 't';
			$sort->default_sort_dir = 'd';

			// If there are categories we are listing as well, only show 10 by default
			if (sizeof($child_categories))
			{
				$sort->default_limit = 10;
			}
			else
			{
				$sort->default_limit = phpbb::$config['topics_per_page'];
			}

			$sort->request();

			// Include the current category in the ones selected
			$child_categories[] = $category_id;

			contribs_overlord::display_contribs('category', $child_categories, $sort);
		}
		else
		{
			// Mark all contribs read
			if (request_var('mark', '') == 'contribs')
			{
				titania_tracking::track(TITANIA_CONTRIB, 0);
			}
			phpbb::$template->assign_vars(array(
				'U_MARK_TOPICS'			=> titania_url::append_url(titania_url::$current_page_url, array('mark' => 'contribs')),
				'L_MARK_TOPICS_READ'	=> phpbb::$user->lang['MARK_CONTRIBS_READ'],
			));

			// Setup the sort tool to sort by update time descending by default
			$sort = new titania_sort();
			$sort->set_sort_keys(contribs_overlord::$sort_by);
			$sort->default_sort_key = 't';
			$sort->default_sort_dir = 'd';
			$sort->default_limit = 10;
			$sort->request();

			contribs_overlord::display_contribs('all', 0, $sort);
		}

		phpbb::$template->assign_vars(array(
			'U_CREATE_CONTRIBUTION'		=> (phpbb::$auth->acl_get('u_titania_contrib_submit')) ? titania_url::build_url('author/' . htmlspecialchars_decode(phpbb::$user->data['username_clean']) . '/create') : '',
			'S_HAS_CONTRIBS'			=> ($categories_ary && $categories_ary[$category_id]['category_type']) ? true : false,
		));

		if ($category_id != 0)
		{
			$category_name = (isset(phpbb::$user->lang[$category_object->category_name])) ? phpbb::$user->lang[$category_object->category_name] : $category_object->category_name;
			titania::page_header($category_name . ' - ' . phpbb::$user->lang['CUSTOMISATION_DATABASE']);
			titania::page_footer(true, 'index_body.html');
		}
	break;
}

titania::page_header('CUSTOMISATION_DATABASE');
titania::page_footer(true, 'index_body.html');
