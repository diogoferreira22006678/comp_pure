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

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Session\Session;
use Joomla\Utilities\ArrayHelper;

function addValueConfig() {
    $config = require JPATH_SITE . '/components/com_pure/config.php';

    $base_url = $config['base_url'];
    $base_api = $config['base_api'];

    return array($base_url, $base_api);
}

list($base_url, $base_api) = addValueConfig();


function post($endpoint, $data, $base_url) {
    $url = $base_url . $endpoint;

    // Create an HTTP object
    $http = JHttpFactory::getHttp();

    // Set the headers
    $headers = array('Content-Type' => 'application/json');

    // Make the POST request
    $response = $http->post($url, json_encode($data), $headers);

    // Check for errors or process the response as needed
    if ($response->code >= 200 && $response->code < 300) {
        return json_decode($response->body, true);
    } else {
        // Handle error
        return null;
    }
}

function getCustom($endpoint, $queryParams = array(), $base_url) {

    $queryString = http_build_query($queryParams);

    $url = $base_url . $endpoint . '?' . $queryString;

    // Create an HTTP object
    $http = JHttpFactory::getHttp();
    $api_key = '47e5bd25-8649-4b60-b8b7-ac0e1381b300'; 

    // Set the headers
    $headers = array(
                    'Content-Type' => 'application/json',
                    'Api-Key' => $api_key
                    );

    // Make the POST request
    $response = $http->get($url, $headers);

    // Check for errors or process the response as needed
    if ($response->code >= 200 && $response->code < 300) {
        return json_decode($response->body, true);
    } else {
        // Show the error
        return 'Gave this error: ' . $response->body . ' and this code: ' . $response->code . ' and this url: ' . $url . ' and this headers: ' . $headers;
    }
}

function institutuions($base_url) {
    return getCustom('organizations', array(), $base_url);
}

$response = institutuions($base_api);
$institutions = $response['items'];

?>

<h1 class="h3">PRL (Pure Research Lusófona) </h1>

<!-- subtitle -->
<h2 class="h4">Institution Filtered By Type</h2>
<form action="<?php echo JRoute::_('index.php?option=com_pure&task=executeApiCall'); ?>" method="post" name="adminForm" id="adminForm">
    <input type="hidden" name="call" value="institutionFilteredTypePersonRoute" />
    <div class="form-group">
        <label for="institution">Institution</label>
        <select class="form-control" id="institution" name="institution">
            <?php foreach ($institutions as $institution) : ?>
                <option value="<?php echo $institution['uuid']; ?>"><?php echo $institution['name']['pt_PT']; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
<hr>
    <button type="submit" class="btn btn-primary">Execute API Call</button>
    <?php echo JHtml::_('form.token'); ?>
</form>





