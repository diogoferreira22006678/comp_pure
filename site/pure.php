<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_PURE
 * @author     Diogo  <diogo.ferreira@ulusofona.pt>
 * @copyright  2024 Diogo 
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\CMS\MVC\Controller\BaseController;

// Include dependancies
jimport('joomla.application.component.controller');

JLoader::registerPrefix('Pure', JPATH_COMPONENT);
JLoader::register('PureController', JPATH_COMPONENT . '/controller.php');


// Execute the task.
$controller = BaseController::getInstance('Pure');
$controller->execute(Factory::getApplication()->input->get('task'));
$controller->redirect();
