<?php
/**
*
* @package Titania
* @version $Id: categories.php 937 2010-03-30 01:21:50Z Tom $
* @copyright (c) 2008 phpBB Customisation Database Team
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_TITANIA'))
{
	exit;
}

if (!phpbb::$auth->acl_gets('u_titania_admin'))
{
	titania::needs_auth();
}

phpbb::$user->add_lang('acp/common');

$category_id = request_var('c', 0);
$submit = (isset($_POST['submit'])) ? true : false;
$action = request_var('action', '');

switch ($action)
{
	case 'add' :
	case 'edit' :
		phpbb::$template->assign_vars(array(
			'CATEGORY' 		=> $category_id,
			'SECTION_NAME'		=> ($action == 'add') ? phpbb::$user->lang['ADD_CATEGORY'] : phpbb::$user->lang['EDIT_CATEGORY'] . ' - ' . $category_name,

			'S_EDIT' 		=> ($action == 'edit') ? true : false,
			'S_ADD' 		=> ($action == 'add') ? true : false,

		));
	break;
	case 'move_up' :
	case 'move_down' :
		$category_object = new titania_category;

		if (!$category_id)
		{
			trigger_error($user->lang['NO_CATEGORY'], E_USER_WARNING);
		}

		$sql = 'SELECT *
			FROM ' . TITANIA_CATEGORIES_TABLE . "
			WHERE category_id = $category_id";
		$result = phpbb::$db->sql_query($sql);
		$row = phpbb::$db->sql_fetchrow($result);
		phpbb::$db->sql_freeresult($result);

		if (!$row)
		{
			trigger_error($user->lang['NO_CATEGORY'], E_USER_WARNING);
		}

		$move_category_name = $category_object->move_category_by($row, $action, 1);

		if ($move_category_name !== false)
		{
			// add_log('admin', 'LOG_FORUM_' . strtoupper($action), $row['category_name'], $move_category_name);
			$cache->destroy('sql', TITANIA_CATEGORIES_TABLE);
		}
		phpbb::$template->assign_vars(array(
			'CATEGORY' 		=> $category_id,

			'S_MOVE' 		=> true,
		));
	break;
	case 'delete' :
		phpbb::$template->assign_vars(array(
			'CATEGORY' 		=> $category_id,
			'SECTION_NAME'		=> phpbb::$user->lang['DELETE_CATEGORY'],

			'S_DELETE' 		=> true,
		));
	break;
	default :
		titania::_include('functions_display', 'titania_display_categories');

		titania_display_categories($category_id);

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
					((isset(phpbb::$user->lang[$categories_ary[$row['category_id']]['category_name']])) ? phpbb::$user->lang[$categories_ary[$row['category_id']]['category_name']] : $categories_ary[$row['category_id']]['category_name'])	=> titania_url::$root_url . $category_object->get_manage_url(),
				));
			}

			// Self
			$category_object->__set_array($categories_ary[$category_id]);
			titania::generate_breadcrumbs(array(
				((isset(phpbb::$user->lang[$categories_ary[$category_id]['category_name']])) ? phpbb::$user->lang[$categories_ary[$category_id]['category_name']] : $categories_ary[$category_id]['category_name'])	=> titania_url::$root_url . $category_object->get_manage_url(),
			));
			unset($categories_ary, $category_object);
		}

		phpbb::$template->assign_vars(array(
			'ICON_MOVE_UP'				=> '<img src="' . titania::$absolute_board . 'adm/images/icon_up.gif" alt="' . phpbb::$user->lang['MOVE_UP'] . '" title="' . phpbb::$user->lang['MOVE_UP'] . '" />',
			'ICON_MOVE_UP_DISABLED'		=> '<img src="' . titania::$absolute_board . 'adm/images/icon_up_disabled.gif" alt="' . phpbb::$user->lang['MOVE_UP'] . '" title="' . phpbb::$user->lang['MOVE_UP'] . '" />',
			'ICON_MOVE_DOWN'			=> '<img src="' . titania::$absolute_board . 'adm/images/icon_down.gif" alt="' . phpbb::$user->lang['MOVE_DOWN'] . '" title="' . phpbb::$user->lang['MOVE_DOWN'] . '" />',
			'ICON_MOVE_DOWN_DISABLED'	=> '<img src="' . titania::$absolute_board . 'adm/images/icon_down_disabled.gif" alt="' . phpbb::$user->lang['MOVE_DOWN'] . '" title="' . phpbb::$user->lang['MOVE_DOWN'] . '" />',
			'ICON_EDIT'					=> '<img src="' . titania::$absolute_board . 'adm/images/icon_edit.gif" alt="' . phpbb::$user->lang['EDIT'] . '" title="' . phpbb::$user->lang['EDIT'] . '" />',
			'ICON_EDIT_DISABLED'		=> '<img src="' . titania::$absolute_board . 'adm/images/icon_edit_disabled.gif" alt="' . phpbb::$user->lang['EDIT'] . '" title="' . phpbb::$user->lang['EDIT'] . '" />',
			'ICON_DELETE'				=> '<img src="' . titania::$absolute_board . 'adm/images/icon_delete.gif" alt="' . phpbb::$user->lang['DELETE'] . '" title="' . phpbb::$user->lang['DELETE'] . '" />',
			'ICON_DELETE_DISABLED'		=> '<img src="' . titania::$absolute_board . 'adm/images/icon_delete_disabled.gif" alt="' . phpbb::$user->lang['DELETE'] . '" title="' . phpbb::$user->lang['DELETE'] . '" />',

			'S_MANAGE' 			=> true,
		));
	break;
}

function trigger_back($message)
{
	$message = (isset(phpbb::$user->lang[$message])) ? phpbb::$user->lang[$message] : $message;

	$message .= '<br /><br /><a href="' . titania_url::build_url('manage/categories') . '">' . phpbb::$user->lang['BACK'] . '</a>';

	trigger_error($message);

}

titania::page_header('MANAGE_CATEGORIES');

titania::page_footer(true, 'manage/categories.html');
