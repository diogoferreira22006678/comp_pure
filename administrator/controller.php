<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_PURE
 * @author     Diogo  <diogo.ferreira@ulusofona.pt>
 * @copyright  2024 Diogo 
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;

/**
 * Class PureController
 *
 * @since  1.6
 */
class PureController extends \Joomla\CMS\MVC\Controller\BaseController
{
	/**
	 * Method to display a view.
	 *
	 * @param   boolean  $cachable   If true, the view output will be cached
	 * @param   mixed    $urlparams  An array of safe url parameters and their variable types, for valid values see {@link JFilterInput::clean()}.
	 *
	 * @return   JController This object to support chaining.
	 *
	 * @since    1.5
     * @throws Exception
	 */
	public function display($cachable = false, $urlparams = false)
	{
		$view = Factory::getApplication()->input->getCmd('view', 'pure');
		Factory::getApplication()->input->set('view', $view);

		parent::display($cachable, $urlparams);

		return $this;
	}

	public function executeApiCall()
	{

		$input = Factory::getApplication()->input;
		$institution = $input->getString('institution');
		$type = $input->getString('type');
		$outerOutputs = $input->get('html_model_outers_outputs', '', 'raw');
		$innerOutputs = $input->get('html_model_inners_outputs', '', 'raw');
		$tableOutputs = $input->get('html_model_table_outputs', '', 'raw');
		$params = array(
			'institution' => $input->getString('institution'),
			'type' => $input->getString('type'),
	
			// Outputs
			'html_model_outers_outputs' => $input->get('html_model_outers_outputs', '', 'raw'),
			'html_model_inners_outputs' => $input->get('html_model_inners_outputs', '', 'raw'),
			'html_model_table_outputs' => $input->get('html_model_table_outputs', '', 'raw'),
	
			// Participantes
			'html_model_inners_participants' => $input->get('html_model_inners_participants', '', 'raw'),
			'html_model_outers_participants' => $input->get('html_model_outers_participants', '', 'raw'),
			'html_model_inners_participants_cord' => $input->get('html_model_inners_participants_cord', '', 'raw'),
	
			// Colaboradores
			'html_model_inners_collaborators' => $input->get('html_model_inners_collaborators', '', 'raw'),
			'html_model_outers_collaborators' => $input->get('html_model_outers_collaborators', '', 'raw'),
		);
		
		// Verificar o token de segurança
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		// Obter o modelo
		$model = $this->getModel('pure');
		if ($model) {
			$model->callApiPure($params);
		} else {
			log("Model not found");
		}
	}

}
