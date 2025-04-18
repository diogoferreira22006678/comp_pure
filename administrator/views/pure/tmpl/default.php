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

// Get a database connection
$db = Factory::getDbo();

// Table name
$prefix = $db->getPrefix();
$tableName = $prefix . 'html_model';
$tableUpdateName = $prefix . 'update_history';

// Check if the table exists
$query = "SHOW TABLES LIKE " . $db->quote($tableName); // Use quote function to properly quote the table name
$db->setQuery($query);
$tableExists = (bool) $db->loadResult();

// Create table if it doesn't exist
if (!$tableExists) {
    // Construct SQL query to create table
    $query = "
            CREATE TABLE $tableName (
                id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                html_model_inners TEXT NOT NULL,
                html_model_outers TEXT NOT NULL,
                html_model_table TEXT NULL
            )
        ";

    $db->setQuery($query);
    $db->execute();
} else {

    $query = "SHOW COLUMNS FROM $tableName LIKE 'html_model_table'";
    $db->setQuery($query);
    $columnExists = (bool) $db->loadResult();

    if (!$columnExists) {
        $query = "ALTER TABLE $tableName ADD COLUMN html_model_table TEXT NULL";
        $db->setQuery($query);
        $db->execute();
    }

    $innerOutputs = "SELECT html_model_inners FROM $tableName WHERE id = 1";
    $db->setQuery($innerOutputs);
    $currentHtmlModelInnersOutputs = $db->loadResult();
    if (empty($currentHtmlModelInnersOutputs)) {
        $currentHtmlModelInnersOutputs =    '<div class="year-section">
                                                [[inner]]
                                            </div>
';
    }

    $OuterOutputs = "SELECT html_model_outers FROM $tableName WHERE id = 1";
    $db->setQuery($OuterOutputs);
    $currentHtmlModelOutersOutputs = $db->loadResult();
    if (empty($currentHtmlModelOutersOutputs)) {
        $currentHtmlModelOutersOutputs = '<table class="research-table">
        <thead>
            <tr>
                <th>[[type]]</th>
            </tr>
        </thead>
        <tbody>
            [[table]]
        </tbody>
        </table>
        ';
    }

    $tableOutputs = "SELECT html_model_table FROM $tableName WHERE id = 1";
    $db->setQuery($tableOutputs);
    $currentHtmlModelTableOutputs = $db->loadResult();
    if (empty($currentHtmlModelTableOutputs)) {
        $currentHtmlModelTableOutputs = '<tr><td><a href="[[link]]">[[title]]</a></td></tr>';
    }


    $innerParticipants = "SELECT html_model_inners FROM $tableName WHERE id = 2";
    $db->setQuery($innerParticipants);
    $currentHtmlModelInnersParticipants = $db->loadResult();
    if (empty($currentHtmlModelInnersParticipants)) {
        $currentHtmlModelInnersParticipants = '<ul class="research-table">[[inner]]</ul>';
    }

    $outerParticipants = "SELECT html_model_outers FROM $tableName WHERE id = 2";
    $db->setQuery($outerParticipants);
    $currentHtmlModelOutersParticipants = $db->loadResult();
    if (empty($currentHtmlModelOutersParticipants)) {
        $currentHtmlModelOutersParticipants = '<ul class="research-table">[[inner]]</ul>';
    
    }

    $tableParticipants = "SELECT html_model_table FROM $tableName WHERE id = 2";
    $db->setQuery($tableParticipants);
    $currentHtmlModelTableParticipants = $db->loadResult();
    if (empty($currentHtmlModelTableParticipants)) {
        $currentHtmlModelTableParticipants = '<li> <a href="[[link]]">[[nome]]</a> - [[Papel]]</li>';
    }

    $innerCollaborators = "SELECT html_model_inners FROM $tableName WHERE id = 3";
    $db->setQuery($innerCollaborators);
    $currentHtmlModelInnersCollaborators = $db->loadResult();
    if (empty($currentHtmlModelInnersCollaborators)) {
        $currentHtmlModelInnersCollaborators = '<ul class="research-table">[[inner]]</ul>';
    }

    $outerCollaborators = "SELECT html_model_outers FROM $tableName WHERE id = 3";
    $db->setQuery($outerCollaborators);
    $currentHtmlModelOutersCollaborators = $db->loadResult();
    if (empty($currentHtmlModelOutersCollaborators)) {
        $currentHtmlModelOutersCollaborators = '<li>[[nome]]</li>';
    }

}

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

<h1 class="h3">Modelo HTML</h1>

<ul>
    <li>Para o modelo HTML, use <code>[[inner]]</code> para indicar onde o conteúdo interno deve ser inserido.</li>
    <li>Exemplo: <code>&lt;ul&gt;[[inner]]&lt;/ul&gt;</code></li>
    <li>Para os modelos de outputs e cursos, use variáveis como <code>[[nome]]</code>, <code>[[email]]</code>, <code>[[telefone]]</code>, etc.</li>
    <li>Exemplo:
        <code>
            &lt;a href="[[link]]" class="docente"&gt;[[nome]]&lt;/a&gt;
        </code>
</ul>

<hr>

<h2 class="h4">Outputs</h2>

<ul>
    <h3 class="h5">Variáveis disponíveis para os outputs:</h3>
    <li><code>[[title]]</code> - Título do output</li>
    <li><code>[[publication-date]]</code> - Data de publicação</li>
    <li><code>[[abstract]]</code> - Resumo</li>
    <li><code>[[pureLink]]</code> - Link para o Pure</li>
    <li><code>[[contributors]]</code> - Autores</li>
    <li><code>[[keywords]]</code> - Palavras-chave</li>
    <li><code>[[tag]]</code> - Se o link tiver ativo então a tag é <code><a></code> senão é <code><span></code></li>
</ul>

<p>Exemplo de uma lista para Outputs: <code>&lt;tr&gt;&lt;td&gt;&lt;[[tag]] href="[[pureLink]]"&gt;[[title]]&lt;/td&gt;&lt;/tr&gt;</code></p>

    <input type="hidden" name="id-outputs" value="1">

    <div class="form-group">
        <label for="exampleFormControlTextarea1">Modelo HTML para os outputs</label>
        <textarea placeholder="Exemplo: <ul>[[inner]]</ul>" class="form-control" id="exampleFormControlTextarea1" rows="3" name="html_model_inners_outputs"><?php echo $currentHtmlModelInnersOutputs; ?></textarea>
    </div>

    <div class="form-group">
        <label for="exampleFormControlTextarea2">Modelo HTML para o conteúdo interno dos outputs Por Ano</label>
        <textarea placeholder='Exemplo: <li><a href="[[pure-link]]">[[title]]</a></li>' class="form-control" id="exampleFormControlTextarea2" rows="3" name="html_model_table_outputs"><?php echo $currentHtmlModelTableOutputs; ?></textarea>
    </div>

    <div class="form-group">
        <label for="exampleFormControlTextarea3">Modelo HTML para a tabela de cada tipo de output dentro de um ano</label>
        <textarea placeholder='Exemplo: <tr><td>[[title]]</td><td>[[publication-date]]</td><td>[[abstract]]</td><td>[[contributors]]</td><td>[[keywords]]</td></tr>' class="form-control" id="exampleFormControlTextarea3" rows="3" name="html_model_outers_outputs"><?php echo $currentHtmlModelOutersOutputs; ?></textarea>
    </div>

    <h2 class="h4">Participantes</h2>
    <ul>
        <h3 class="h5">Variáveis disponíveis para os participantes:</h3>
        <li><code>[[nome]]</code> - Nome do participante</li>
        <li><code>[[Papel]]</code> - Papel no projeto</li>
        <li><code>[[pureLink]]</code> - Link para o Pure do participante</li>
    </ul>
    <p>Exemplo de uma lista para Participantes: <code>&lt;li&gt;&lt;a href="[[pureLink]]"&gt;[[nome]]&lt;/a&gt; - [[Papel]]&lt;/li&gt;</code></p>

    <input type="hidden" name="id-participants" value="2">
    <div class="form-group">
        <label for="exampleFormControlTextarea4">Modelo HTML para os participantes</label>
        <textarea placeholder='Exemplo: <ul>[[inner]]</ul>' class="form-control" id="exampleFormControlTextarea4" rows="3" name="html_model_inners_participants"><?php echo $currentHtmlModelTableParticipants; ?></textarea>
    </div>

    <div class="form-group">
        <label for="exampleFormControlTextarea6">Modelo HTML para os participantes que cordenam</label>
        <textarea placeholder='Exemplo: <tr><td>[[nome]]</td><td>[[Papel]]</td></tr>' class="form-control" id="exampleFormControlTextarea5" rows="3" name="html_model_inners_participants_cord"><?php echo $currentHtmlModelInnersParticipants; ?></textarea>
    </div>

    <div class="form-group">
        <label for="exampleFormControlTextarea5">Modelo HTML para o conteúdo interno dos participantes</label>
        <textarea placeholder='Exemplo: <li><a href="[[pure-link]]">[[nome]]</a> - [[Papel]]</li>' class="form-control" id="exampleFormControlTextarea6" rows="3" name="html_model_outers_participants"><?php echo $currentHtmlModelOutersParticipants; ?></textarea>
    </div>

    <h2 class="h4">Colaboradores</h2>

    <ul>
        <h3 class="h5">Variáveis disponíveis para os colaboradores:</h3>
        <li><code>[[nome]]</code> - Nome do colaborador</li>
    </ul>

    <p>Exemplo de uma lista para Colaboradores: <code>&lt;li&gt;[[nome]]&lt;/li&gt;</code></p>

    <input type="hidden" name="id-collaborators" value="3">
    <div class="form-group">
        <label for="exampleFormControlTextarea7">Modelo HTML para os colaboradores</label>
        <textarea placeholder='Exemplo: <ul>[[inner]]</ul>' class="form-control" id="exampleFormControlTextarea7" rows="3" name="html_model_inners_collaborators"><?php echo $currentHtmlModelInnersCollaborators; ?></textarea>
    </div>

    <div class="form-group">
        <label for="exampleFormControlTextarea8">Modelo HTML interno para os colaboradores</label>
        <textarea placeholder='Exemplo: <tr><td>[[nome]]</td></tr>' class="form-control" id="exampleFormControlTextarea8" rows="3" name="html_model_outers_collaborators"><?php echo $currentHtmlModelOutersCollaborators; ?></textarea>
    </div>

    <button type="submit" class="btn btn-primary">Execute API Call</button>
    <?php echo JHtml::_('form.token'); ?>
    
</form>





