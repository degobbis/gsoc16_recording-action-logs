<?php
/**
 * @package	 Joomla.Plugins
 * @subpackage  System.userlogs
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license	 GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;
JLoader::register('UserlogsHelper', JPATH_ADMINISTRATOR . '/components/com_userlogs/helpers/userlogs.php');

/**
 * Joomla! Users Actions Logging Plugin.
 *
 * @since  3.7
 */
class PlgSystemUserLogs extends JPlugin
{
	/**
	 * Array of loggable extensions.
	 *
	 * @var    array
	 * @since  3.6
	 */
	protected $loggable_extensions = array();

	/**
	 * Application object.
	 *
	 * @var    JApplicationCms
	 * @since  3.6
	 */
	protected $app;

	/**
	 * Database object.
	 *
	 * @var    JDatabaseDriver
	 * @since  3.6
	 */
	protected $db;

	/**
	 * Constructor.
	 *
	 * @param   object  &$subject  The object to observe.
	 * @param   array   $config    An optional associative array of configuration settings.
	 *
	 * @since   3.6
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		if (is_array($this->params->get('loggable_extensions')))
		{
			$this->loggable_extensions = $this->params->get('loggable_extensions');
		}

		else
		{
			$this->loggable_extensions = explode(',', $this->params->get('loggable_extensions'));
		}
	}

	/**
	 * Function to add logs to the database
	 * This method adds a record to #__user_logs contains (message, date, context, user)
	 *
	 * @param   string   $message  The contents of the message to be logged
	 * @param   string   $context  The context of the content passed to the plugin
	 *
	 * @return  void
	 *
	 * @since   3.7
 	*/
	protected function addLogsToDb($message, $context)
	{
		$user = JFactory::getUser();
		$date = JFactory::getDate();
		$dispatcher = JEventDispatcher::getInstance();
		$query = $this->db->getQuery(​true​);

		if ($this->params->get('ip_logging'))
		{
			$ip_address = $this->app->input->server->get('REMOTE_ADDR');
		}
		else
		{
			$ip_address = JText::_('PLG_SYSTEM_USERLOG_DISABLED');
		}

		$columns = array('message', 'log_date', 'extension', 'user_id', 'ip_address');
		$values = array($this->db->quote($message), $this->db->quote($date),
			$this->db->quote($context), $this->db->quote($user->id), $this->db->quote($ip_address)
		);
		$query
			->insert($this->db->quoteName('#__user_logs'))
			->columns($this->db->quoteName($columns))
			->values(implode(',', $values));
		$this->db->setQuery($query);
		$this->db->execute();
		$dispatcher->trigger('onUserLogsAfterMessageLog', array ($message, $date, $context, $user->name, $ip_address));
	}

	/**
	 * Function to check if a component is loggable or not
	 *
	 * @param   string   $extension  The extension that triggered the event
	 *
	 * @return  boolean
	 *
	 * @since   3.7
	 */
	protected function checkLoggable($extension)
	{
		if (!in_array($extension, $this->loggable_extensions))
		{
			return false;
		}

		return true;
	}

	/**
	 * After save content logging method
	 * This method adds a record to #__user_logs contains (message, date, context, user)
	 * Method is called right after the content is saved
	 *
	 * @param   string   $context  The context of the content passed to the plugin
	 * @param   object   $article  A JTableContent object
	 * @param   boolean  $isNew	If the content is just about to be created
	 *
	 * @return  boolean   true if function not enabled, is in front-end or is new. Else true or
	 *					false depending on success of save function.
	 *
	 * @since   3.7
	 */
	public function onContentAfterSave($context, $content, $isNew)
	{
		if ($this->checkLoggable($this->app->input->get('option')))
		{
			$isNew_string = $isNew ? 'true' : 'false';
			$parameters = UserlogsHelper::getLogMessageParams($context);
			$title_holder = "";
			$title_type = "";

			if ($parameters)
			{
				$title_holder = $content->get($parameters->title_holder);
				$type_title = $parameters->type_title;
			}

			$message = '{"title":"' . $title_holder . '","isNew":"' . $isNew_string . '", "event":"onContentAfterSave",'
				. '"type":"' . $type_title . '"}';
			$strContext = (string) $context;
			$this->addLogsToDb($message, $strContext);
		}
	}

	/**
	 * After delete content logging method
	 * This method adds a record to #__user_logs contains (message, date, context, user)
	 * Method is called right after the content is deleted
	 *
	 * @param   string   $context  The context of the content passed to the plugin
	 * @param   object   $article  A JTableContent object
	 *
	 * @return  void
	 *
	 * @since   3.7
	 */
	public function onContentAfterDelete($context, $content)
	{
		if ($this->checkLoggable($this->app->input->get('option')))
		{
			$parameters = UserlogsHelper::getLogMessageParams($context);
			$title_holder = "";
			$title_type = "";

			if ($parameters)
			{
				$title_holder = $content->get($parameters->title_holder);
				$type_title = $parameters->type_title;
			}

			$message = '{"title":"' . $title_holder . '","event":"onContentAfterDelete",'
				. '"type":"' . $type_title . '"}';
			$strContext = (string) $context;
			$this->addLogsToDb($message, $strContext);
		}
	}

	/**
	 * On content change status logging method
	 * This method adds a record to #__user_logs contains (message, date, context, user)
	 * Method is called when the status of the article is changed
	 *
	 * @param   string   $context  The context of the content passed to the plugin
	 * @param   array	$pks	  An array of primary key ids of the content that has changed state.
	 * @param   int	  $value	The value of the state that the content has been changed to.
	 *
	 * @return  void
	 *
	 * @since   3.7
	 */
	public function onContentChangeState($context, $pks, $value)
	{
		if ($this->checkLoggable($this->app->input->get('option')))
		{
			$parameters = UserlogsHelper::getLogMessageParams($context);
			$titles = array();

			if ($parameters)
			{
				$table_values = json_decode($parameters->table_values, true);
				$titles = UserlogsHelper::getDataByPks($pks, $parameters->title_holder, $table_values['table_type'], $table_values['table_prefix']);
			}

			$message = '{"event":"onContentChangeState",'
						. '"type":"' . $parameters->type_title . '",'
						. '"title":"' . implode('\",\"', $titles) . '","value":' . (string) $value . '}';
			$strContext = (string) $context;
			$this->addLogsToDb($message, $strContext);
		}
	}

	/**
	 * On installing extensions logging method
	 * This method adds a record to #__user_logs contains (message, date, context, user)
	 * Method is called when an extension is installed
	 *
	 * @param   JInstaller  $installer  Installer object
	 * @param   integer	 $eid		Extension Identifier
	 *
	 * @return  void
	 *
	 * @since   3.7
	 */
	public function onExtensionAfterInstall($installer, $eid)
	{
		$context = $this->app->input->get('option');

		if ($this->checkLoggable($context))
		{
			$message = '{"event":"onExtensionAfterInstall","extenstion_name":"' . $installer->get('manifest')->name .
				'","extenstion_type":"' . $installer->get('manifest')->attributes()['type'] . '"}';
$this->addLogsToDb($message, $context);
		}
	}

	/**
	 * On uninstalling extensions logging method
	 * This method adds a record to #__user_logs contains (message, date, context, user)
	 * Method is called when an extension is uninstalled
	 *
	 * @param   JInstaller  $installer  Installer instance
	 * @param   integer	 $eid		Extension id
	 * @param   integer	 $result	 Installation result
	 *
	 * @return  void
	 *
	 * @since   3.7
	 */
	public function onExtensionAfterUninstall($installer, $eid, $result)
	{
		$context = $this->app->input->get('option');

		if ($this->checkLoggable($context))
		{
			$message = '{"event":"onExtensionAfterUninstall","extenstion_name":"' . $installer->get('manifest')->name
				. '","extenstion_type":"' . $installer->get('manifest')->attributes()['type'] . '"}';
			$this->addLogsToDb($message, $context);
		}
	}

	/**
	 * On updating extensions logging method
	 * This method adds a record to #__user_logs contains (message, date, context, user)
	 * Method is called when an extension is updated
	 *
	 * @param   JInstaller  $installer  Installer instance
	 * @param   integer	 $eid		Extension id
	 *
	 * @return  void
	 *
	 * @since   3.7
	 */
	public function onExtensionAfterUpdate($installer, $eid)
	{
		$context = $this->app->input->get('option');

		if ($this->checkLoggable($context))
		{
			$message = '{"event":"onExtensionAfterUpdate","extenstion_name":"' . $installer->get('manifest')->name .
				'","extenstion_type":"' . $installer->get('manifest')->attributes()['type'] . '"}';
			$this->addLogsToDb($message, $context);
		}
	}

	/**
	 * On Saving extensions logging method
	 * Method is called when an extension is being saved
	 *
	 * @param   string   $context   The extension
	 * @param   JTable   $table	 DataBase Table object
	 * @param   boolean  $isNew	 If the extension is new or not
	 *
	 * @return  void
	 *
	 * @since   3.7
	 */
	public function onExtensionAfterSave($context, $table, $isNew)
	{
		if ($this->checkLoggable($this->app->input->get('option')))
		{
			$parameters = UserlogsHelper::getLogMessageParams($context);
			$title_holder = "";
			$title_type = "";

			if ($parameters)
			{
				$title_holder = $content->get($parameters->title_holder);
				$type_title = $parameters->type_title;
			}

			$isNew_string = $isNew ? 'true' : 'false';
			$message = '{"title":"' . $table->get($parameters->title_holder) . '","isNew":"' . $isNew_string . '", "event":"onExtensionAfterSave",' .
				'"type":"' . $parameters->type_title . '"}';
			$this->addLogsToDb($message, $context);
		}
	}

	/**
	 * On Deleting extensions logging method
	 * Method is called when an extension is being deleted
	 *
	 * @param   string  $context	The extension
	 * @param   JTable	 $table   DataBase Table object
	 *
	 * @return  void
	 *
	 * @since   3.7
	 */
	public function onExtensionAfterDelete($context, $table)
	{
		if ($this->checkLoggable($this->app->input->get('option')))
		{
			$isNew_string = $isNew ? 'true' : 'false';
			$message = '{"event":"onExtensionAfterDelete","title":"' . $table->title . '"}';
			$this->addLogsToDb($message, $context);
		}
	}

	/**
	 * On saving user data logging method
	 *
	 * Method is called after user data is stored in the database.
	 * This method logs who created/edited any user's data
	 *
	 * @param   array	$user	 Holds the new user data.
	 * @param   boolean  $isnew	True if a new user is stored.
	 * @param   boolean  $success  True if user was succesfully stored in the database.
	 * @param   string   $msg	  Message.
	 *
	 * @return  void
	 *
	 * @since   3.7
	 */
	public function onUserAfterSave($user, $isnew, $success, $msg)
	{
		$context = $this->app->input->get('option');

		if ($this->checkLoggable($context))
		{
			$isNew_string = $isnew ? 'true' : 'false';
			$success_string = $success ? 'true' : 'false';
			$message = '{"edited_user":"' . $user["name"] . '","event":"onUserAfterSave",' .
						'"isNew":"' . $isNew_string . '","success":"' . $success_string . '", "user_id":' . $user["id"] . '}';
			$this->addLogsToDb($message, $context);
		}
	}

	/**
	 * On deleting user data logging method
	 *
	 * Method is called after user data is deleted from the database
	 *
	 * @param   array	$user	 Holds the user data
	 * @param   boolean  $success  True if user was succesfully stored in the database
	 * @param   string   $msg	  Message
	 *
	 * @return  boolean
	 */
	public function onUserAfterDelete($user, $success, $msg)
	{
		$context = $this->app->input->get('option');

		if ($this->checkLoggable($context))
		{
			$success_string = $success ? 'true' : 'false';
			$message = '{"deleted_user":"' . $user["name"] . '","event":"onUserAfterDelete",' .
					'"success":"' . $success_string . '", "user_id":' . $user["id"] . '}';
			$this->addLogsToDb($message, $context);
		}
	}

	/**
  * On deleting user group data logging method
  *
  * Method is called after user data is deleted from the database
  *
  * @param   array	$group	 Holds the group data
  * @param   boolean  $success  True if user was succesfully stored in the database
  * @param   string   $msg	  Message
  *
  * @return  boolean
  */
	public function onUserAfterSaveGroup($context, $table, $isNew)
	{
		$context = $this->app->input->get('option');

		if ($this->checkLoggable($context))
		{
			$isNew_string = $isNew ? 'true' : 'false';
			$message = '{"title":"' . $table->title . '","event":"onUserAfterSaveGroup",' .
						'"isNew":"' . $isNew_string . '"}';
			$this->addLogsToDb($message, $context);
		}
	}

	/**
	 * On deleting user group data logging method
	 *
	 * Method is called after user data is deleted from the database
	 *
	 * @param   array	$group	 Holds the group data
	 * @param   boolean  $success  True if user was succesfully stored in the database
	 * @param   string   $msg	  Message
	 *
	 * @return  boolean
	 */
	public function onUserAfterDeleteGroup($group, $success, $msg)
	{
		$context = $this->app->input->get('option');

		if ($this->checkLoggable($context))
		{
			$success_string = $success ? 'true' : 'false';
			$message = '{"deleted_group":"' . $group["title"] . '","event":"onUserAfterDeleteGroup",' .
						'"success":"' . $success_string . '", "group_id":' . $group["id"] . '}';
			$this->addLogsToDb($message, $context);
		}
	}

	/**
	 * Method is called before writing logs message to make it more readable
	 *
	 * @param   string   $message	  Message
	 * @param   string   $extension	Extension that caused this log
	 *
	 * @return  boolean
  */
	public function onLogMessagePrepare(&$message, $extension)
	{
		JPlugin::loadLanguage();

		$extension = UserlogsHelper::translateExtensionName(strtoupper(strtok($extension, '.')));

		$extension = preg_replace('/s$/', '', $extension);
		$message_to_array = json_decode($message, true);

		if (!empty($message_to_array['type']))
		{
			$type = 'PLG_SYSTEM_USERLOG_TYPE_' . strtoupper($message_to_array['type']);
		}

		switch ($message_to_array['event'])
		{
			case 'onContentAfterSave':

				if ($message_to_array[ 'isNew' ] == 'false')
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOG_ON_CONTENT_AFTER_SAVE_MESSAGE', ucfirst(JText::_($type)));
				}
				else
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOG_ON_CONTENT_AFTER_SAVE_NEW_MESSAGE', JText::_($type));
				}

				if (!empty($message_to_array['title']))
				{
					$message = $message . JText::sprintf('PLG_SYSTEM_USERLOG_TITLED', $message_to_array['title']);
				}

break;
			case 'onContentAfterDelete':
				$message = JText::sprintf('PLG_SYSTEM_USERLOG_ON_CONTENT_AFTER_DELETE_MESSAGE', ucfirst(JText::_($type)));

				if (!empty($message_to_array['title']))
				{
					$message = $message . JText::sprintf('PLG_SYSTEM_USERLOG_TITLED', $message_to_array['title']);
				}
				break;
			case 'onContentChangeState':
				if ($message_to_array['value'] == 0)
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOG_ON_CONTENT_CHANGE_STATE_UNPUBLISHED_MESSAGE',
						ucfirst(JText::_($type)), $message_to_array['title']
					);
				}
				elseif ($message_to_array['value'] == 1)
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOG_ON_CONTENT_CHANGE_STATE_PUBLISHED_MESSAGE',
						ucfirst(JText::_($type)), $message_to_array['title']
					);
				}
				elseif ($message_to_array['value'] == 2)
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOG_ON_CONTENT_CHANGE_STATE_ARCHIVED_MESSAGE',
						ucfirst(JText::_($type)), $message_to_array['title'], $message_to_array['title']
					);
				}
				elseif ($message_to_array['value'] == -2)
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOG_ON_CONTENT_CHANGE_STATE_TRASHED_MESSAGE',
						ucfirst(JText::_($type)), $message_to_array['title']
					);
				}
				break;
			case 'onExtensionAfterInstall':
				$message = JText::sprintf('PLG_SYSTEM_USERLOG_ON_EXTENSION_AFTER_INSTALL_MESSAGE', $message_to_array['extenstion_name']);
				break;
			case 'onExtensionAfterUninstall':
				$message = JText::sprintf('PLG_SYSTEM_USERLOG_ON_EXTENSION_AFTER_UNINSTALL_MESSAGE', $message_to_array['extenstion_name']);
				break;
			case 'onExtensionAfterUpdate':
				$message = JText::sprintf('PLG_SYSTEM_USERLOG_ON_EXTENSION_AFTER_UPDATE_MESSAGE', $message_to_array['extenstion_name']);
				break;
			case 'onUserAfterSave':
				if ($message_to_array['isNew'] == 'false')
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOG_ON_USER_AFTER_SAVE_MESSAGE', $message_to_array['edited_user']);
				}
				else
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOG_ON_USER_AFTER_SAVE_NEW_MESSAGE', $message_to_array['edited_user']);
				}
				break;
			case 'onUserAfterDelete':
				$message = JText::sprintf('PLG_SYSTEM_USERLOG_ON_USER_AFTER_DELETE_MESSAGE', $message_to_array['edited_user']);
				break;
			case 'onUserAfterSaveGroup':
				if ($message_to_array['isNew'] == 'false')
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOG_ON_USER_AFTER_SAVE_GROUP_MESSAGE', $message_to_array['title']);
				}
				else
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOG_ON_USER_AFTER_SAVE_GROUP_NEW_MESSAGE', $message_to_array['title']);
				}
				break;
			case 'onUserAfterDeleteGroup':
				$message = JText::sprintf('PLG_SYSTEM_USERLOG_ON_USER_AFTER_DELETE_GROUP_MESSAGE', $message_to_array['deleted_group']);
				break;
			case 'onExtensionAfterSave':
				if ($message_to_array['isNew'] == 'false')
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOG_ON_EXTENSION_AFTER_SAVE_MESSAGE', ucfirst(JText::_($type)));
				}
				else
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOG_ON_EXTENSION_AFTER_SAVE_NEW_MESSAGE', JText::_($type));
				}

				if (!empty($message_to_array['title']))
				{
					$message = $message . JText::sprintf('PLG_SYSTEM_USERLOG_TITLED', $message_to_array['title']);
				}
				break;
			case 'onExtensionAfterDelete':
				$message = JText::sprintf('PLG_SYSTEM_USERLOG_ON_EXTENSION_AFTER_DELETE_MESSAGE', $extension);

				if (!empty($message_to_array['title']))
				{
					$message = $message . JText::sprintf('PLG_SYSTEM_USERLOG_TITLED', $message_to_array['title']);
				}
				break;
		}
	}

	/**
  * Adds additional fields to the user editing form for logs e-mail notifications
  *
  * @param   JForm  $form  The form to be altered.
  * @param   mixed  $data  The associated data for the form.
  *
  * @return  boolean
  *
  * @since   1.6
  */
	public function onContentPrepareForm($form, $data)
	{
		$lang = JFactory::getLanguage();
		$lang->load('plg_system_userlogs', JPATH_ADMINISTRATOR);

		if (!$form instanceof JForm)
		{
			$this->subject->setError('JERROR_NOT_A_FORM');

			return false;
		}

		if (!in_array($form->getName(), array('com_users.profile', 'com_users.registration','com_users.user','com_admin.profile')))
		{
			return true;
		}

		if ($form->getName() == 'com_admin.profile'
			|| $form->getName() == 'com_users.profile')
		{
			JForm::addFormPath(dirname(__FILE__) . '/profiles');
			$form->loadFile('profile', false);

			if (!JFactory::getUser()->authorise('core.viewlogs'))
			{
				$form->removeField('newsletter_option');
				$form->removeField('newsletter_extensions');
			}
		}
	}

	/**
	 * Method called after event log is stored to database
	 *
	 * @param   array  $values	The data logged to the database
	 *
	 * @return  boolean
	 */
	public function onUserLogsAfterMessageLog($message, $date, $context, $user_name, $ip_address)
	{
		$dispatcher = JEventDispatcher::getInstance();
		$query = $this->db->getQuery(true);
		$query->select('a.email, a.params');
		$query->from($this->db->quoteName('#__users', 'a'));
		$query->where($this->db->quoteName('params') . ' LIKE ' . $this->db->quote('%"logs_notification_option":"1"%'));
		$this->db->setQuery($query);
		$this->db->execute();

		$users = $this->db->loadObjectList();

		if (empty($users))
		{
			return;
		}

		$recipients = array();

		foreach ($users as $user)
		{
			$extensions = json_decode($user->params, true)['logs_notification_extensions'];

			if (in_array(strtok($context, '.'), $extensions))
			{
				$recipients[] = $user->email;
			}
		}

		if (empty($recipients))
		{
			return;
		}

		$dispatcher->trigger('onLogMessagePrepare', array (&$message, $context));
		$body = '<h1>'
			. JText::_('PLG_SYSTEM_USERLOG_EMAIL_SUBJECT') .
			'</h1><h2>'
			. JText::_('PLG_SYSTEM_USERLOG_EMAIL_DESC') .
			'</h2><table><thead>
					<th>' . JText::_('COM_USERLOGS_MESSAGE') . '</th>
					<th>' . JText::_('COM_USERLOGS_DATE') . '</th>
					<th>' . JText::_('COM_USERLOGS_EXTENSION') . '</th>
					<th>' . JText::_('COM_USERLOGS_USER') . '</th>
					<th>' . JText::_('COM_USERLOGS_IP_ADDRESS') . '</th>
				</thead><tbody><tr>
						<td>' . $message . '</td>
						<td>' . $log_date . '</td>
						<td>' . UserlogsHelper::translateExtensionName(strtoupper(strtok($extension), '.')) . '</td>
						<td>' . $user_name . '</td>
						<td>' . JText::_($ip_address) . '</td>
			</tr></tbody></table>';
		$mailer = JFactory::getMailer();

		$config = JFactory::getConfig();
		$sender = array(
			$config->get('mailfrom'),
			$config->get('fromname')
		);
		$mailer->setSender($sender);
		$mailer->addRecipient($recipients);
		$mailer->setSubject(JText::_('PLG_SYSTEM_USERLOG_EMAIL_SUBJECT'));
		$mailer->isHTML(true);
		$mailer->Encoding = 'base64';
		$mailer->setBody($body);
		$send = $mailer->Send();
	}
}