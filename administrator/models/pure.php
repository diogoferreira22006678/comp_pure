<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_PURE
 * @author     Diogo  <diogo.ferreira@ulusofona.pt>
 * @copyright  2024 Diogo 
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Log\Log;

ini_set('max_execution_time', 300); // Set a reasonable execution time
ini_set('memory_limit', '512M');    // Set a reasonable memory limit

/**
 * Methods supporting a list of Pure records.
 *
 * @since  1.6
 */
JLoader::register('JTableCategory', JPATH_PLATFORM . '/joomla/database/table/category.php');

class PureModelPure extends \Joomla\CMS\MVC\Model\ListModel {

    protected $base_url = 'https://research.ulusofona.pt/ws/api/';
    protected $categories = [
        'academic' => ['title' => 'Academic', 'alias' => 'academic', 'description' => 'Academic category'],
        'non-academic' => ['title' => 'Non-academic', 'alias' => 'non-academic', 'description' => 'Non-academic category'],
        'phd' => ['title' => 'PhD', 'alias' => 'phd', 'description' => 'PhD category'],
        'other' => ['title' => 'Other', 'alias' => 'other', 'description' => 'Other category'],
        'visitingScholar' => ['title' => 'Visiting Scholar', 'alias' => 'visiting-scholar', 'description' => 'Visiting Scholar category'],
        'honoraryStaff' => ['title' => 'Honorary Staff', 'alias' => 'honorary-staff', 'description' => 'Honorary Staff category']
    ];
    protected $categoryResearchOutput = [
        'researchOutput' => ['title' => 'Research Output', 'alias' => 'research-output', 'description' => 'Research Output category']
    ];
    protected $institutionArray = [
        'institution' => ['title' => 'Institution', 'alias' => 'institution', 'description' => 'Institution category']
    ];
    protected $projectArray = [
        'project' => ['title' => 'Project', 'alias' => 'project', 'description' => 'Project category']
    ];
    protected $personArray = [];
    protected $researchOutputArray = [];
    protected $modelOutputs = [];

    /**
     * Method to auto-populate the model state.
     *
     * @param   string  $ordering   Elements order
     * @param   string  $direction  Order direction
     *
     * @return void
     *
     * @throws Exception
     */
    protected function populateState($ordering = null, $direction = null)
    {
        parent::populateState('a.id', 'ASC');
        $context = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $context);
        JLoader::register('FieldsHelper', JPATH_ADMINISTRATOR . '/components/com_fields/helpers/fields.php');

        if (!empty($context)) {
            $parts = FieldsHelper::extract($context);
            if ($parts) {
                $this->setState('filter.component', $parts[0]);
                $this->setState('filter.section', $parts[1]);
            }
        }
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return string A store id.
     *
     * @since 1.6
     */
    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.state');
        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return   JDatabaseQuery
     *
     * @since 1.6
     */
    protected function getListQuery()
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true);
        return $query;
    }

    /**
     * Get an array of data items
     *
     * @return mixed Array of data items on success, false on failure.
     */
    public function getItems()
    {
        $items = parent::getItems();
        return $items;
    }

    // function to save output models
    public function updateHtmlModelsOutputs($html_model_outers, $html_model_inners, $id, $html_model_table = '')
	{
		$this->saveHtmlModel($html_model_outers, $html_model_inners, $id, $html_model_table);
	}

    protected function saveHtmlModel($html_model_outers, $html_model_inners, $id, $html_model_table = '')
	{
		// check if id exists in #__html_model table and update it, if not insert it
		$db = Factory::getDbo();
		$query = $db->getQuery(true);
		$query->select($db->quoteName('id'))
			->from($db->quoteName('#__html_model'))
			->where($db->quoteName('id') . ' = ' . $db->quote($id));
		$db->setQuery($query);
		$result = $db->loadResult();

		if ($result) {
			$query = $db->getQuery(true);
			$fields = array(
				$db->quoteName('html_model_outers') . ' = ' . $db->quote($html_model_outers),
				$db->quoteName('html_model_inners') . ' = ' . $db->quote($html_model_inners),
				$db->quoteName('html_model_table') . ' = ' . $db->quote($html_model_table)
			);
			$conditions = array(
				$db->quoteName('id') . ' = ' . $db->quote($id)
			);
			$query->update($db->quoteName('#__html_model'))->set($fields)->where($conditions);
			$db->setQuery($query);
			$db->execute();
		} else {
			$query = $db->getQuery(true);
			$fields = array(
				$db->quoteName('id') . ' = ' . $db->quote($id),
				$db->quoteName('html_model_outers') . ' = ' . $db->quote($html_model_outers),
				$db->quoteName('html_model_inners') . ' = ' . $db->quote($html_model_inners),
				$db->quoteName('html_model_table') . ' = ' . $db->quote($html_model_table)
			);
			$query->insert($db->quoteName('#__html_model'))->set($fields);
			$db->setQuery($query);
			$db->execute();
		}
	}

    /*-------------------------------------------API INTEGRATION----------------------------------------------------------------*/

    /**
     * Calls Pure API to handle persons and research outputs.
     *
     * @param array $params Parameters including institution and types
     * @return bool
     */
    public function callApiPure($params)
    {
        try {
            // Outputs
            $outputInners = $params['html_model_inners_outputs'];
            $outputOuters = $params['html_model_outers_outputs'];
            $outputTable = $params['html_model_table_outputs'];
            $this->updateHtmlModelsOutputs($outputOuters, $outputInners, 1, $outputTable);

            // Participantes
            $participantInners = $params['html_model_inners_participants'];
            $participantOuters = $params['html_model_outers_participants'];
            $participantCord = $params['html_model_inners_participants_cord'];
            $this->updateHtmlModelsOutputs($participantOuters, $participantCord, 2, $participantInners);

            // Colaboradores
            $collabInners = $params['html_model_inners_collaborators'];
            $collabOuters = $params['html_model_outers_collaborators'];
            $this->updateHtmlModelsOutputs($collabOuters, $collabInners, 3, null);

            // Guardar os modelos para referência, se necessário
            $this->modelOutputs = [
                'html_model_inners_outputs' => $outputInners,
                'html_model_outers_outputs' => $outputOuters,
                'html_model_table_outputs' => $outputTable,
                'html_model_inners_participants' => $participantInners,
                'html_model_outers_participants' => $participantOuters,
                'html_model_inners_participants_cord' => $participantCord,
                'html_model_inners_collaborators' => $collabInners,
                'html_model_outers_collaborators' => $collabOuters
            ];

            $this->ensureGroupFields();
            
            // $this->institutionFilteredTypePersonRoute($params['institution']);
            // $this->researchOutputsFilteredTypeRoute($params['institution']);
            $this->projectFilteredTypeRoute($params['institution']);

        } catch (Exception $e) {
            print_r('Error in callApiPure: ' . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Fetches persons of specified types from an institution.
     *
     * @param string $institution
     * @param array $types
     */
    public function institutionFilteredTypePersonRoute($institution)
    {
        $response = $this->institutionDependentsFilteredByPerson($institution);
        $persons = $response['items'];
        $data = [];

        foreach ($persons as $person) {
            if ($person['systemName'] !== 'Person') continue;

            $person_uuid = $person['uuid'];
            $person_response = $this->person($person_uuid);
            // Find the correct association for the given institution
            $person_type = '';
            foreach ($person_response['staffOrganizationAssociations'] as $association) {
                if ($association['organization']['uuid'] === $institution) {
                    $person_type = $association['staffType']['term']['en_GB'] ?? 'Other';
                    break;
                }
            }
            $profile_photo = $this->getProfilePhoto($person_response['profilePhotos']);
            $name_variant = $this->getNameVariant($person_response['name']);
            $email = $this->getUserEmail($person_uuid);
            $orcid_id = $person_response['orcid'] ?? '';
            [$scopus_author_id, $ciencia_vitae_id] = $this->getIdentifiers($person_response['identifiers']);
            $biography = $person_response['profileInformation'][0]['value']['pt_PT'] ?? '';
            $pure_link = $person_response['portalUrl'] ?? '';

            $data[] = [
                'uuid' => $person_uuid,
                'profile-photo' => $profile_photo,
                'name-variant' => $name_variant,
                'email' => $email,
                'orcid-id' => $orcid_id,
                'ciencia-vitae-id' => $ciencia_vitae_id,
                'scopus-author-id' => $scopus_author_id,
                'biography' => $biography,
                'pure-link' => $pure_link,
                'type' => $person_type
            ];

        }

        $this->processPersonsData($data);
    }

    public function projectFilteredTypeRoute($institution)
    {
        $response = $this->institutionDependentsFilteredByPerson($institution);
        $projects = $response['items'];
        $data = [];
        foreach ($projects as $project) {

            if ($project['systemName'] !== 'Project') continue;
    
            $project_response = $this->project($project['uuid']);

            $participants = $this->processHtmlParticipants($project_response['participants']);
            $collaborators = $this->processHtmlCollaborators($project_response['collaborators']);
            $leading_partner = $collaborators['leading_partner'];
            $collaborators = $collaborators['collaborators'];

            // Debug: Print the final collaborators string
            echo "Collaborators: " . $collaborators . "\n";

            echo "Project Reference: " . $project_response['identifiers'][0]['id'] . "\n";
            
            $data = [
                'uuid-project' => $project['uuid'],
                'portal-link' => $project_response['portalUrl'] ?? '',
                'title-project' => $project_response['title']['en_GB'] ?? 'No Title Available',
                'acronym' => $project_response['acronym'] ?? 'No Acronym',
                'project-reference' => $project_response['identifiers'][0]['id'] ?? 'No Reference',
                'funding-total' => $project_response['funding']['total'] ?? 'No Funding Info',
                'funding-programme' => $project_response['fundingProgramme'] ?? 'No Programme Info',
                'start-date' => $project_response['period']['startDate'] ?? 'Unknown',
                'end-date' => $project_response['period']['endDate'] ?? 'Unknown',
                'status' => $project_response['status'] ?? 'No Status Info',
                'leading-partner' => $leading_partner,
                'description' => isset($project_response['descriptions'][0]['value']['en_GB'])
                    ? $project_response['descriptions'][0]['value']['en_GB']
                    : 'No Description Available',
                'participants' => $participants,
                'collaborators' => $collaborators
            ];
        
            $this->createArticleProject($data, $this->ensureCategoryExists($this->projectArray['project']));
        }
    }
    

    /**
     * Fetches and processes research outputs for an institution.
     *
     * @param string $institution
     */
    public function researchOutputsFilteredTypeRoute($institution)
    {
        // Fetch dependents by institution
        $response = $this->institutionDependentsFilteredByPerson($institution);
        $research_outputs = $response['items'];
    
        // Translation dictionary
        $translations = [
            'pt-PT' => [
                'no_year' => 'Sem Ano',
                'no_type' => 'Sem Tipo',
                'no_name' => 'Sem Nome',
                'title' => 'Título',
                'publication_date' => 'Data de Publicação',
                'contributors' => 'Contribuidores',
                'keywords' => 'Palavras-chave',
                'abstract' => 'Resumo',
                'research_outputs' => 'Resultados de Pesquisa'
            ],
            'en-GB' => [
                'no_year' => 'No Year',
                'no_type' => 'No Type',
                'no_name' => 'No Name',
                'title' => 'Title',
                'publication_date' => 'Publication Date',
                'contributors' => 'Contributors',
                'keywords' => 'Keywords',
                'abstract' => 'Abstract',
                'research_outputs' => 'Research Outputs'
            ]
        ];
    
        // Retrieve translations based on the selected language
        $lang = 'en-GB'; // Set default language, can be dynamic
        $trans = $translations[$lang] ?? $translations['en-GB']; // Default to English if language not found
    
        // Group research outputs by year and type
        $groupedData = [];
        foreach ($research_outputs as $research_output) {
            if ($research_output['systemName'] !== 'ResearchOutput') {
                continue;
            }
    
            $research_output_uuid = $research_output['uuid'];
            $research_output_response = $this->researchOutput($research_output_uuid);
            $formattedData = $this->formatResearchOutputData($research_output_response);
            
            // Extract year and type
            $year = $formattedData['publication-date'] ?? $trans['no_year'];
            $type = $formattedData['type'] ?? $trans['no_type'];
    
            // Initialize the structure if not set
            if (!isset($groupedData[$year])) {
                $groupedData[$year] = [];
            }
            if (!isset($groupedData[$year][$type])) {
                $groupedData[$year][$type] = [];
            }
    
            // Add the formatted data to the appropriate year and type
            $groupedData[$year][$type][] = $formattedData;
        }

        $arrayHtmlByYear = [];
        // Process each year/type group into a separate table
        foreach ($groupedData as $year => $types) {

            // if year is not set then dont create the table
            if($year == 'Sem Ano' || $year == 'No Year') {
                continue;
            }

            // Create the year heading
            $tableRows = "<h2>{$trans['research_outputs']} - " . htmlspecialchars($year) . "</h2>";
    
            foreach ($types as $type => $outputs) {

                // Get the user's HTML models
                $innerTemplate = $this->modelOutputs['html_model_inners_outputs']; // Inner template wraps the list
                $outerTemplate = $this->modelOutputs['html_model_outers_outputs']; // Outer template formats individual items
                $tableTemplate = $this->modelOutputs['html_model_table_outputs']; // Table template for grouping

                foreach ($outputs as $output) {

                    // transform title into APA format
                    
                    
                    $placeholders = [
                        'title' => $output['title'],
                        'publication-date' => $output['publication-date'],
                        'abstract' => $output['abstract'],
                        'pureLink' => $output['pure-link'],
                        'contributors' => $output['contributors-research-output'],
                        'keywords' => $output['keywords'],
                        
                    ];

                    $tableRow = preg_replace_callback("/\[\[([\w]+)\]\]/", function ($matches) use ($placeholders) {
                        $placeholder = $matches[1];
                        return isset($placeholders[$placeholder]) ? $placeholders[$placeholder] : $matches[0];
                    }, $outerTemplate);

                    $tableRows .= "<tr>$tableRow</tr>";
                }

                // Wrap the outer content using the inner template (list)
                $renderedInnerContent = str_replace('[[inner]]', $tableRows, $innerTemplate);

                // Add the type and year placeholders to the table template
                $tableWithTypeAndYear = str_replace(
                    ['[[type]]', '[[year]]'],
                    [htmlspecialchars($type), htmlspecialchars($year)],
                    $tableTemplate
                );

                // Wrap the rendered inner content using the table template (table)
                $finalOutput = str_replace('[[table]]', $renderedInnerContent, $tableWithTypeAndYear);
            }

            $this->createArticleResearchOutput($finalOutput, $this->ensureCategoryExists($this->categoryResearchOutput['researchOutput']), $year);
        }
    
        // Return the final grouped tables HTML
        return $arrayHtmlByYear;
    }


    public function processHtmlParticipants($participants)
    {
        // Modelos HTML personalizados
        $innerCoord = $this->modelOutputs['html_model_inners_participants_cord'];       // <ul> para coordenação
        $innerGeneral = $this->modelOutputs['html_model_inners_participants'];          // <ul> para outros
        $itemTemplate = $this->modelOutputs['html_model_outers_participants'];          // <li> com tags
    
        $coordinators = '';
        $others = '';
    
        foreach ($participants as $participant) {

           // Obter dados de nome e papel
            $firstName = ucfirst($participant['name']['firstName'] ?? '');
            $lastName = ucfirst($participant['name']['lastName'] ?? '');
            $fullName = trim($firstName . ' ' . $lastName);
            $role = $participant['role']['term']['en_GB'] ?? 'Unknown';

            // Link para o Pure, se existir
            $pureLink = '#';
            if (isset($participant['person']['uuid'])) {
                $personData = $this->person($participant['person']['uuid']);
                $pureLink = $personData['portalUrl'] ?? '#';
            }

            // Mapear para placeholders
            $placeholders = [
                'nome' => $fullName,
                'Papel' => $role,
                'pureLink' => $pureLink
            ];
    
            // Substituir placeholders no template
            $renderedItem = preg_replace_callback("/\[\[([\w]+)\]\]/", function ($matches) use ($placeholders) {
                $key = $matches[1];
                return isset($placeholders[$key]) ? $placeholders[$key] : $matches[0];
            }, $itemTemplate);

            // Verificar se é PI ou CoPI
            $roleSlug = strtolower($role);
            if ($roleSlug === 'pi' || $roleSlug === 'copi' || $roleSlug === 'project manager') {
                $coordinators .= $renderedItem;
            } else {
                $others .= $renderedItem;
            }
        }

        // Renderizar blocos finais
        $htmlCoord = !empty($coordinators) ? str_replace('[[inner]]', $coordinators, $innerCoord) : '';
        $htmlOthers = !empty($others) ? str_replace('[[inner]]', $others, $innerGeneral) : '';

        return $htmlCoord . $htmlOthers;
    }

    public function processHtmlCollaborators($collaborators)
    {
        // Modelos HTML personalizados
        $innerTemplate = $this->modelOutputs['html_model_inners_collaborators'];   // Ex: <ul>[[inner]]</ul>
        $itemTemplate = $this->modelOutputs['html_model_outers_collaborators'];    // Ex: <li>[[nome]]</li>
    
        $collabList = '';
        $leading_partner = '';
    
        foreach ($collaborators as $collaborator) {
            if (isset($collaborator['externalOrganization']['uuid'])) {
                $uuid = $collaborator['externalOrganization']['uuid'];
    
                // Buscar nome da organização externa
                $organization = $this->externalOrganization($uuid);
                $name = $organization['name']['en_GB'] ?? 'Unknown';
                if ($collaborator['leadCollaborator'] == true) {
                    $leading_partner = $name;
                }
    
                // Substituir [[nome]] no template
                $renderedItem = str_replace('[[nome]]', $name, $itemTemplate);
    
                $collabList .= $renderedItem;
            }
        }

        if (isset($collaborator['externalOrganization']['uuid'])) {
            // check if leadCollaborator is set to true
            if ($collaborator['leadCollaborator'] == true) {
                $leading_partner = $this->externalOrganization($collaborator['externalOrganization']['uuid'])['name']['en_GB'];
                // Debug: Print leading partner name
                echo "Leading Partner: " . $leading_partner . "\n";
                break;
            }
        }

        $collaborators = str_replace('[[inner]]', $collabList, $innerTemplate);

        $result = [
            'collaborators' => $collaborators,
            'leading_partner' => $leading_partner
        ];
    
        // Envolver com o modelo <ul>[[inner]]</ul>
        return $result;
    }
    
            
    
    private function generateApaCitation(array $data): string
    {
        $contributors = $this->formatContributorsAPA($data['contributors'] ?? []);
        $year = $data['publicationStatuses'][0]['publicationDate']['year'] ?? 'n.d.';
        $title = $data['title']['value'] ?? 'No title';
    
        // Journal info
        $journal = $data['journalAssociation']['title']['title'] ?? null;
        $volume = $data['volume'] ?? null;
        $issue = $data['journalNumber'] ?? null;
        $pages = $data['pages'] ?? null;
    
        // Get DOI from correct electronicVersion
        $doi = null;
        if (isset($data['electronicVersions'])) {
            foreach ($data['electronicVersions'] as $ev) {
                if ($ev['typeDiscriminator'] === 'DoiElectronicVersion' && isset($ev['doi'])) {
                    $doi = $ev['doi'];
                    break;
                }
            }
        }
    
        $link = $doi ? "https://doi.org/{$doi}" : ($data['portalUrl'] ?? '');
    
        // Build journal info
        $journal_info = '';
        if ($journal) {
            $journal_info = "<i>{$journal}</i>";
            if ($volume) {
                $journal_info .= ", <i>{$volume}</i>";
            }
            if ($issue) {
                $journal_info .= "({$issue})";
            }
            if ($pages) {
                $journal_info .= ", {$pages}";
            }
        }
    
        return "{$contributors} ({$year}). <i>{$title}</i>. {$journal_info}. {$link}";
    }
        

    /**
     * Formats research output data for article creation.
     *
     * @param array $research_output_response
     * @return array
     */
    private function formatResearchOutputData($research_output_response)
    {
        //$title = $this->formatTitleAPA($research_output_response['title']['value'] ?? 'No Title Available');
        $publication_date = $research_output_response['publicationStatuses'][0]['publicationDate']['year'] ?? 'Unknown';
        $abstract = $research_output_response['abstract']['pt_PT'] ?? 'No abstract available';
        $pure_link = $research_output_response['portalUrl'] ?? '';
        $keywords = $this->getKeywords($research_output_response['keywordGroups']);
        $type = $research_output_response['type']['term']['en_GB'] ?? 'No Type';
        $contributors = $this->formatContributorsAPA($research_output_response['contributors']);
        $apa_citation = $this->generateApaCitation($research_output_response);

        $html = file_get_contents($pure_link);

        // if ($html !== false) {
        //     $doc = new DOMDocument();
        //     @$doc->loadHTML($html);

        //     // Assuming the APA citation is inside a div with class 'citation'
        //     $xpath = new DOMXPath($doc);
        //     $citation = $xpath->query('//div[@id="cite-apa"]');

        //     if ($citation->length > 0) {
        //         $title = $citation->item(0)->nodeValue;
        //     } else {
        //         echo "APA citation not found.";
        //     }
        // } else {
        //     echo "Failed to fetch the portal link.";
        // }


        return [
            'uuid' => $research_output_response['uuid'],
            'title' => $apa_citation,
            'publication-date' => $publication_date,
            'abstract' => $abstract,
            'pure-link' => $pure_link,
            'contributors' => $contributors,
            'keywords' => $keywords,
            'type' => $type,
            'contributors-research-output' => $contributors
        ];
    }

    // Function to format titles in APA style
    private function formatTitleAPA($title)
    {
        // Convert the title to lowercase and capitalize only the first word and proper nouns
        $words = explode(' ', strtolower($title));
        foreach ($words as $key => $word) {
            if ($key === 0 || $this->isProperNoun($word)) {
                $words[$key] = ucfirst($word);
            }
        }
        return implode(' ', $words);
    }

    // Function to determine if a word is a proper noun
    private function isProperNoun($word)
    {
        // Add logic to check for proper nouns (e.g., predefined list, capitalized in source data)
        // For simplicity, assuming proper nouns are words already capitalized
        return ctype_upper($word[0]);
    }

    // Function to format contributors in APA style
    private function formatContributorsAPA($participants)
    {
        $formattedContributors = [];


        foreach ($participants as $participant) {

            print_r($participant);

            if (isset($participant['uuid'])) {
                $uuid = $participant['uuid']; // Internal person UUID
            } elseif (isset($contributor['externalPerson']['uuid'])) {
                $uuid = $contributor['externalPerson']['uuid']; // External person UUID
            }

            // Ensure firstName and lastName exist
            $firstName = $participant['name']['firstName'] ?? '';
            $lastName = $participant['name']['lastName'] ?? '';
    
            // Capitalize first letter of each name
            $firstName = ucwords(strtolower($firstName));
            $lastName = ucwords(strtolower($lastName));
    
            // Extract the initial from the first name
            $initial = isset($firstName[0]) ? strtoupper($firstName[0]) . '.' : '';
    
            // if uuid then add the portal link
            if (isset($uuid)) {
                $portalUrl = $this->person($uuid)['portalUrl'];
                $formattedContributors[] = '<a href="' . $portalUrl . '">' . $lastName . ', ' . $initial . '</a>';
            } else {
                $formattedContributors[] =  $lastName . ', ' . $initial;
            }
        }
    
        return implode(', ', $formattedContributors);
    }
    


    /**
     * Process and create articles for persons data.
     *
     * @param array $data
     */
    private function processPersonsData($data)
    {
        $categoriesIds = $this->getCategoriesIds();
        foreach ($data as $person) {
            $personType = $person['type'];
    
            $categoryId = $this->getPersonCategoryId($personType, $categoriesIds);
    
            $this->createArticlePerson($person, $categoryId);
        }
    }
    

    /**
     * Ensures all required group fields exist.
     */
    private function ensureGroupFields()
    {
        try {
            $this->ensureGroupFieldForPerson();
            $this->ensureGroupFieldForInstitution();
            $this->ensureGroupFieldForResearchOutput();
            $this->ensureGroupFieldForProject();
        } catch (Exception $e) {
            print_r('Failed to create group field: ' . $e->getMessage());
        }
    }

    /**
     * Retrieves the category ID for a given person based on their type.
     *
     * @param string $person_type
     * @param array $categoriesIds
     * @return int
     */
    private function getPersonCategoryId($person_type, $categoriesIds)
    {
        $mapping = [
            'Academic' => 'academic',
            'Non-academic' => 'non-academic',
            'PhD' => 'phd',
            'Other' => 'other',
            'Visiting Scholar' => 'visitingScholar',
            'Honorary staff' => 'honoraryStaff'
        ];
    
        $categoryKey = $mapping[$person_type] ?? 'other';
        $categoryId = $categoriesIds[$categoryKey] ?? null;
    
        return $categoryId;
    }

    /**
     * Retrieves all category IDs.
     *
     * @return array
     */
    private function getCategoriesIds()
    {
        $categoriesIds = [];
        foreach ($this->categories as $category) {
            $categoriesIds[$category['alias']] = $this->ensureCategoryExists($category);
        }
        $this->ensureCategoryExists($this->institutionArray['institution']);
        $this->ensureCategoryExists($this->categoryResearchOutput['researchOutput']);
        return $categoriesIds;
    }

    /**
     * Retrieves profile photo URL from profile photos array.
     *
     * @param array $profilePhotos
     * @return string
     */
    private function getProfilePhoto($profilePhotos)
    {
        foreach ($profilePhotos as $profilePhoto) {
            if ($profilePhoto['type']['uri'] === '/dk/atira/pure/person/personfiles/portrait') {
                return $profilePhoto['url'];
            }
        }
        return '';
    }

    private function project($uuid)
    {
        $endpoint = 'projects/' . $uuid;
        return $this->getCustom($endpoint);
    }

    public function externalOrganization($uuid)
    {
        $endpoint = 'external-organizations/' . $uuid;
        return $this->getCustom($endpoint);
    }

    /**
     * Retrieves name variant or constructs it from first and last names.
     *
     * @param array $name
     * @return string
     */
    private function getNameVariant($name)
    {
        return $name['firstName'] . ' ' . $name['lastName'];
    }

    /**
     * Retrieves user email by UUID.
     *
     * @param string $uuid
     * @return string
     */
    private function getUserEmail($uuid)
    {
        $user = $this->user($uuid);
        return $user['email'] ?? '';
    }

    /**
     * Retrieves identifiers (Scopus, Ciencia Vitae) from identifier array.
     *
     * @param array $identifiers
     * @return array
     */
    private function getIdentifiers($identifiers)
    {
        $scopus_author_id = '';
        $ciencia_vitae_id = '';

        foreach ($identifiers as $identifier) {
            switch ($identifier['type']['uri']) {
                case '/dk/atira/pure/person/personsources/scopusauthor':
                    $scopus_author_id = $identifier['id'];
                    break;
                case '/dk/atira/pure/person/personsources/cienciavitae':
                    $ciencia_vitae_id = $identifier['id'];
                    break;
            }
        }

        return [$scopus_author_id, $ciencia_vitae_id];
    }

    /**
     * Retrieves keywords from keyword groups array.
     *
     * @param array $keywordGroups
     * @return array
     */
    private function getKeywords($keywordGroups)
    {
        $keywords = [];
        foreach ($keywordGroups as $keywordGroup) {
            if (isset($keywordGroup['keywords'])) {
                foreach ($keywordGroup['keywords'] as $keywordSet) {
                    if (isset($keywordSet['freeKeywords'])) {
                        $keywords = array_merge($keywords, $keywordSet['freeKeywords']);
                    }
                }
            }
        }
        return $keywords;
    }

		/**
	 * Retrieves fields by group ID from Joomla's fields table.
	 *
	 * @param int $groupId The ID of the field group.
	 * @return array An associative array of fields with field names as keys.
	 */
	private function getFieldsByGroup($groupId)
	{
		$db = Factory::getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName(['id', 'name', 'title']))
			->from($db->quoteName('#__fields'))
			->where($db->quoteName('group_id') . ' = ' . (int) $groupId)
			->where($db->quoteName('state') . ' = 1'); // Only active fields

		$db->setQuery($query);

		try {
			// Return fields indexed by their name
			return $db->loadAssocList('name');
		} catch (Exception $e) {
			print_r('Failed to retrieve fields: ' . $e->getMessage());
			return [];
		}
	}


    /*-------------------------------------------CATEGORY MANAGEMENT----------------------------------------------------------------*/

    /**
     * Ensures a category exists; creates it if it does not exist.
     *
     * @param array $categoryData
     * @param bool $checkOnly
     * @return int
     * @throws Exception
     */
    protected function ensureCategoryExists($categoryData, $checkOnly = false)
    {
        if (empty($categoryData['title']) || empty($categoryData['alias'])) {
            throw new Exception('Invalid category data provided.');
        }

        $categoryTable = JTable::getInstance('Category');
        $category = $categoryTable->load(['alias' => $categoryData['alias'], 'extension' => 'com_content']);
        $categoryId = $categoryTable->id;

        if ($checkOnly) return $categoryId;

        if (!$category) {
            $categoryTable->bind($categoryData);
            $categoryTable->published = 1;
            $categoryTable->access = 1;
            $categoryTable->params = '{"category_layout":"","image":""}';
            $categoryTable->metadata = '{"page_title":"","author":"","robots":""}';
            $categoryTable->language = '*';
            $categoryTable->extension = 'com_content';
            $categoryTable->setLocation(1, 'last-child');

            if (!$categoryTable->store()) {
                throw new Exception('Failed to create category: ' . $categoryTable->getError());
            }
        }

        return $categoryTable->id;
    }

    /*-------------------------------------------FIELDS AND GROUPS MANAGEMENT------------------------------------------------------*/

    /**
     * Ensures that the group field for persons exists, creates if missing.
     *
     * @param string $groupName
     * @param bool $onlyCheck
     * @return int|array
     */
    public function ensureGroupFieldForPerson($groupName = 'Person', $onlyCheck = false)
    {
        return $this->ensureGroupField($groupName, 'com_content.article', $this->categories, $onlyCheck, 'Person');
    }

    /**
     * Ensures that the group field for institutions exists, creates if missing.
     *
     * @param string $groupName
     * @param bool $onlyCheck
     * @return int|array
     */
    private function ensureGroupFieldForInstitution($groupName = 'Institution', $onlyCheck = false)
    {
        return $this->ensureGroupField($groupName, 'com_content.article', $this->institutionArray, $onlyCheck, 'Institution');
    }

    /**
     * Ensures that the group field for research outputs exists, creates if missing.
     *
     * @param string $groupName
     * @param bool $onlyCheck
     * @return int|array
     */
    private function ensureGroupFieldForResearchOutput($groupName = 'Research Output', $onlyCheck = false)
    {
        return $this->ensureGroupField($groupName, 'com_content.article', $this->categoryResearchOutput, $onlyCheck, 'Research Output');
    }

    /**
     * Ensures that the group field for projects exists, creates if missing.
     *
     * @param string $groupName
     * @param bool $onlyCheck
     * @return int|array
     */
    private function ensureGroupFieldForProject($groupName = 'Project', $onlyCheck = false)
    {
        return $this->ensureGroupField($groupName, 'com_content.article', $this->projectArray, $onlyCheck, 'Project');
    }

    /**
     * Generic method to ensure a group field exists.
     *
     * @param string $groupName
     * @param string $context
     * @param array $categories
     * @param bool $onlyCheck
     * @return int|array
     */
    private function ensureGroupField($groupName, $context, $categories, $onlyCheck = false, $type = 'Person')
    {
        \JLoader::registerNamespace('Joomla\CMS', JPATH_LIBRARIES . '/src');
        $app = Factory::getApplication();

        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__fields_groups'))
            ->where($db->quoteName('title') . ' = ' . $db->quote($groupName));
        $db->setQuery($query);
        $groupId = $db->loadResult();

        if ($onlyCheck) return $groupId;

        if (!$groupId) {
            \JLoader::register('FieldsModelGroup', JPATH_ADMINISTRATOR . '/components/com_fields/models/group.php');
            $model = $app->bootComponent('com_fields')->getMVCFactory()->createModel('Group', 'Administrator', ['ignore_request' => true]);
            $data = [
                'title' => $groupName,
                'state' => 1,
                'access' => 1,
                'context' => $context,
                'language' => '*',
                'created_by' => Factory::getUser()->id,
                'params' => '{"show_label":1,"label":"' . $groupName . '","description":"","required":0,"language":""}',
                'description' => $groupName . ' group field',
            ];

            if (!$model->save($data)) {
                $error = $model->getError();
                return ['error' => 'Error creating group field: ' . $error];
            }

            $groupId = $model->getState($model->getName() . '.id');
        }

        switch ($type) {
            case 'Person':
                $this->ensureFieldsExist($groupId, $categories, $type);
                break;
            case 'Institution':
                $this->ensureFieldsExist($groupId, $categories, $type);
                break;
            case 'Research Output':
                $this->ensureFieldsExist($groupId, $categories, $type);
                break;
            case 'Project':
                $this->ensureFieldsExist($groupId, $categories, $type);
                break;
        }

        return $groupId;
    }

    /**
     * Ensures required fields exist in a group.
     *
     * @param int $groupId
     * @param array $categories
     */
    private function ensureFieldsExist($groupId, $categories, $type)
    {
        $fields = $this->getFieldDefinitions($type);

        foreach ($fields as $fieldInfo) {
            $this->createOrUpdateField($groupId, $fieldInfo, $categories);
        }
    }

    /**
     * Provides field definitions for creation.
     *
     * @return array
     */
    private function getFieldDefinitions($type)
    {

        switch ($type) {
            case 'Person':
                return [
                    ['title' => 'UUID', 'name' => 'uuid'],
                    ['title' => 'Biography', 'name' => 'biography', 'filter' => 'html'],
                    ['title' => 'ORCID ID', 'name' => 'orcid-id'],
                    ['title' => 'Scopus Author ID', 'name' => 'scopus-author-id'],
                    ['title' => 'Ciencia Vitae ID', 'name' => 'ciencia-vitae-id'],
                    ['title' => 'Profile Photo', 'name' => 'profile-photo'],
                    ['title' => 'Link to Pure', 'name' => 'pure-link'],
                    ['title' => 'Email', 'name' => 'email'],
                    ['title' => 'Name Variant', 'name' => 'name-variant']
                ];
            case 'Institution':
                return [
                ];
            case 'Research Output':
                return [
                    ['title' => 'Research Outputs', 'name' => 'research-outputs'],
                    ['title' => 'Contributors', 'name' => 'contributors-research-output'],
                ];
            case 'Project':
                return [
                    ['title' => 'UUID', 'name' => 'uuid-project'],
                    ['title' => 'Title', 'name' => 'title-project'],
                    ['title' => 'Acronym', 'name' => 'acronym'],
                    ['title' => 'Project Reference', 'name' => 'project-reference'],
                    ['title' => 'Funding (Total)', 'name' => 'funding-total'],
                    ['title' => 'Funding Programme', 'name' => 'funding-programme'],
                    ['title' => 'Start Date', 'name' => 'start-date'],
                    ['title' => 'End Date', 'name' => 'end-date'],
                    ['title' => 'Status', 'name' => 'status'],
                    ['title' => 'Description', 'name' => 'description', 'filter' => 'html'],
                    ['title' => 'Leading Partner', 'name' => 'leading-partner'],
                    ['title' => 'Portal Link', 'name' => 'portal-link'],
                    ['title' => 'Collaborators', 'name' => 'collaborators'],
                    ['title' => 'Participants', 'name' => 'participants']
                ];
            default:
                return [];
        
        }
    }

    /**
     * Creates or updates a field for a group.
     *
     * @param int $groupId
     * @param array $fieldInfo
     * @param array $categories
     */
    private function createOrUpdateField($groupId, $fieldInfo, $categories)
    {
        try {
            // Determine field type
            $fieldInfo['type'] = ($fieldInfo['name'] === 'profile-photo') ? 'media' : 'textarea';
    
            // Validate field information
            if (empty($fieldInfo['name']) || empty($fieldInfo['title'])) {
                throw new Exception('Invalid field info: Name and Title are required.');
            }
    
            // Check if the field already exists
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__fields'))
                ->where($db->quoteName('name') . ' = ' . $db->quote($fieldInfo['name']))
                ->where($db->quoteName('context') . ' = ' . $db->quote('com_content.article'))
                ->where($db->quoteName('group_id') . ' = ' . $db->quote($groupId));
            $db->setQuery($query);
            $existingFieldId = $db->loadResult();
    
            if ($existingFieldId) {
                print_r('Field already exists: ' . $fieldInfo['name']);
                return $existingFieldId;
            }
    
            // Create the field if it does not exist
            return $this->createField($groupId, $fieldInfo, $categories);
    
        } catch (Exception $e) {
            print_r('Error in createOrUpdateField: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Creates a field and associates it with categories.
     *
     * @param int $groupId
     * @param array $fieldInfo
     * @param array $categories
     * @return int|null The ID of the created field, or null on failure.
     */
    private function createField($groupId, $fieldInfo, $categories)
    {
        \JLoader::register('FieldsModelField', JPATH_ADMINISTRATOR . '/components/com_fields/models/field.php');
        $app = Factory::getApplication();

        try {
            // Default parameters
            $params = [
                'show_label' => '1',
                'required' => '0',
                'language' => '*'
            ];

            // Additional parameters for specific field types
            $fieldparams = [];
            if ($fieldInfo['type'] === 'media') {
                $fieldparams = [
                    'image_directory' => 'images', // Restrict uploads to the images directory
                    'allowed_extensions' => 'jpg,jpeg,png,gif', // Allowed image types
                    'media_type' => 'image' // Restrict media type to images
                ];
            } elseif (isset($fieldInfo['filter']) && $fieldInfo['filter'] === 'html') {
                $fieldparams = [
                    'filter' => 'safehtml',
                    'maxlength' => ''
                ];
            }

            // Prepare field data
            $data = [
                'title' => $fieldInfo['title'],
                'name' => $fieldInfo['name'],
                'label' => $fieldInfo['title'],
                'type' => $fieldInfo['type'] ?? 'textarea', // Default to textarea if type not provided
                'context' => 'com_content.article',
                'group_id' => $groupId,
                'state' => 1,
                'access' => 1,
                'language' => '*',
                'created_by' => Factory::getUser()->id,
                'params' => json_encode($params),
                'fieldparams' => json_encode($fieldparams),
                'description' => $fieldInfo['title'] . ' field',
            ];

            // Create the field
            $fieldModel = $app->bootComponent('com_fields')->getMVCFactory()->createModel('Field', 'Administrator', ['ignore_request' => true]);
            if (!$fieldModel->save($data)) {
                throw new Exception('Error creating field: ' . $fieldModel->getError());
            }

            // Get the created field ID
            $fieldId = $fieldModel->getState('field.id');

            // Associate the field with categories
            $this->associateFieldWithCategories($fieldId, $categories);

            return $fieldId;

        } catch (Exception $e) {
            print_r('Error in createField: ' . $e->getMessage());
            return null;
        }
    }


    /**
     * Associates a field with multiple categories.
     *
     * @param int $fieldId
     * @param array $categories
     */
    private function associateFieldWithCategories($fieldId, $categories)
    {
        foreach ($categories as $category) {
            $categoryId = $this->ensureCategoryExists($category, true);
            if ($categoryId) {
                $this->createFieldCategoryRelation($fieldId, $categoryId);
            }
        }
    }

    /**
     * Creates a field-category relation in the database.
     *
     * @param int $fieldId
     * @param int $categoryId
     */
    private function createFieldCategoryRelation($fieldId, $categoryId)
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $query->select('COUNT(*)')
            ->from($db->quoteName('#__fields_categories'))
            ->where([$db->quoteName('field_id') . ' = ' . $db->quote($fieldId), $db->quoteName('category_id') . ' = ' . $db->quote($categoryId)]);
        $db->setQuery($query);

        if ($db->loadResult()) return;

        $query = $db->getQuery(true);
        $query->insert($db->quoteName('#__fields_categories'))
            ->columns($db->quoteName(['field_id', 'category_id']))
            ->values($db->quote($fieldId) . ', ' . $db->quote($categoryId));
        $db->setQuery($query);
        $db->execute();
    }

    /*-------------------------------------------ARTICLE CREATION--------------------------------------------------------------*/

    /**
     * Creates or updates an article for a person.
     *
     * @param array $person
     * @param int $categoryId
     */
    public function createArticlePerson($person, $categoryId)
    {
        $app = Factory::getApplication();

        try {
            $info = $this->generateTitle($person['name-variant'], $person['uuid']);
            $assetId = $this->getArticleViaAlias($info['alias']);
            $introtext = $assetId ? $this->deleteArticleById($assetId) : '';

            // check if title and alias are valid, if not put a default value with a random number
            if (empty($info['title']) || empty($info['alias'])) {
                $info['title'] = 'Person' . rand(1, 1000);
                $info['alias'] = 'person-' . rand(1, 1000);
            }

            $data = [
                'title' => $info['title'],
                'alias' => $info['alias'],
                'introtext' => $introtext,
                'catid' => $categoryId,
                'state' => 1,
                'language' => '*',
                'access' => 1,
                'created_by' => Factory::getUser()->id,
            ];

            $groupFieldId = $this->ensureGroupFieldForPerson('Person', true);
            $fields = $this->getFieldsByGroup($groupFieldId);

            $model = $app->bootComponent('com_content')->getMVCFactory()->createModel('Article', 'Administrator', ['ignore_request' => true]);
            if (!$model->save($data)) {
                throw new Exception('Failed to save article: ' . $model->getError() . ' - with name: ' . $info['title']);
            }

            $articleId = $model->getState('article.id');
            $person['article_id'] = $articleId;
            $this->personArray[] = $person;

            $this->updateFieldsForArticle($fields, $person, $articleId);

        } catch (Exception $e) {
            print_r('Failed to create person article: ' . $e->getMessage());
        }
    }

    /**
     * Creates or updates an article for research outputs.
     *
     * @param array $researchOutput
     * @param int $categoryId
     */
    public function createArticleResearchOutput($researchOutput, $categoryId, $year)
    {
        $app = Factory::getApplication();

        try {
            $info = $this->generateTitle('Research Outputs', $year);
            $assetId = $this->getArticleViaAlias($info['alias']);
            $introtext = $assetId ? $this->deleteArticleById($assetId) : '';

            $data = [
                'title' => $info['title'] . ' - ' . $year,
                'alias' => $info['alias'],
                'introtext' => $introtext,
                'catid' => $categoryId,
                'state' => 1,
                'language' => '*',
                'access' => 1,
                'created_by' => Factory::getUser()->id,
            ];

            $groupFieldId = $this->ensureGroupFieldForResearchOutput('Research Output', true);
            $fields = $this->getFieldsByGroup($groupFieldId);

            $model = $app->bootComponent('com_content')->getMVCFactory()->createModel('Article', 'Administrator', ['ignore_request' => true]);
            if (!$model->save($data)) {
                throw new Exception('Failed to save article: ' . $model->getError() . ' - with name: ' . $info['title']);
            }

            $articleId = $model->getState('article.id');

            $this->updateFieldsForArticle($fields, ['research-outputs' => $researchOutput], $articleId);

        } catch (Exception $e) {
            print_r('Failed to create research output article: ' . $e->getMessage());
        }
    }

    /**
     * Creates or updates an article for a project.
     *
     * @param array $project
     * @param int $categoryId
     */

    public function createArticleProject($project, $categoryId){
        $app = Factory::getApplication();

        try {
            $info = $this->generateTitle($project['title-project'], $project['uuid-project']);
            $assetId = $this->getArticleViaAlias($info['alias']);
            $introtext = $assetId ? $this->deleteArticleById($assetId) : '';

            // check if title and alias are valid, if not put a default value with a random number
            if (empty($info['title']) || empty($info['alias'])) {
                print_r('Invalid title or alias for project: ' . $project['title-project'] . ' - ' . $project['uuid-project']);
                return;
            }

            $data = [
                'title' => $info['title'],
                'alias' => $info['alias'],
                'introtext' => $introtext,
                'catid' => $categoryId,
                'state' => 1,
                'language' => '*',
                'access' => 1,
                'created_by' => Factory::getUser()->id,
            ];

            $groupFieldId = $this->ensureGroupFieldForProject('Project', true);
            $fields = $this->getFieldsByGroup($groupFieldId);

            $model = $app->bootComponent('com_content')->getMVCFactory()->createModel('Article', 'Administrator', ['ignore_request' => true]);
            if (!$model->save($data)) {
                throw new Exception('Failed to save article: ' . $model->getError() . ' - with name: ' . $info['title']);
            }

            $articleId = $model->getState('article.id');
            $project['article_id'] = $articleId;
            $this->projectArray[] = $project;

            $this->updateFieldsForArticle($fields, $project, $articleId);

        } catch (Exception $e) {
            print_r('Failed to create project article: ' . $e->getMessage());
        }
    }

    /**
     * Updates fields for a specific article.
     *
     * @param array $fields
     * @param array $data
     * @param int $articleId
     */
    private function updateFieldsForArticle($fields, $data, $articleId)
    {
        foreach ($fields as $field) {
            $fieldValue = $data[$field['name']] ?? '';
            if ($fieldValue) {
                // if its profile photo, we need to download the image and save it to the server
                if ($field['name'] === 'profile-photo') {
                    $info = $this->generateTitle($data['name-variant'], $data['uuid']);
                    $fieldValue = $this->downloadImage($fieldValue, $articleId, $info);
                }
                $this->updateArticleWithCustomField($field['id'], $articleId, $fieldValue);
            }
        }
    }

    /**
     * Downloads an image from a URL requiring an API key and saves it to the server.
     *
     * @param string $url The image URL.
     * @param int $articleId The article ID for naming the saved image.
     * @param array $info Additional info for file naming.
     * @return string The local path to the saved image.
     */
    private function downloadImage($url, $articleId, $info)
    {
        // Define the API key
        $apiKey = '47e5bd25-8649-4b60-b8b7-ac0e1381b300'; // Replace with your actual API key
    
        // Set up the HTTP context with the API key
        $context = stream_context_create([
            'http' => [
                'header' => "Api-Key: $apiKey\r\n",
                'ignore_errors' => true, // To capture errors in response
            ]
        ]);
    
        // Fetch the image data
        $imageData = file_get_contents($url, false, $context);
    
        // Check for errors during the fetch
        if ($imageData === false) {
            print_r("Failed to download image from URL: $url");
            return '';
        }
    
        // Validate the response headers for HTTP errors
        $headers = $http_response_header ?? [];
        foreach ($headers as $header) {
            if (strpos($header, 'HTTP/') === 0) {
                $statusCode = (int)explode(' ', $header)[1];
                if ($statusCode !== 200) {
                    print_r("HTTP error while fetching image: $statusCode for URL: $url");
                    return '';
                }
            }
        }
    
        // Validate that the data is an image
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($imageData);
        if (strpos($mimeType, 'image/') !== 0) {
            print_r("Invalid image data fetched from URL: $url");
            return '';
        }
    
        // Save the image locally
        $filename = $info['alias'] . '.jpg';
        $path = JPATH_SITE . '/images/' . $filename;
    
        // If the file already exists, delete it
        if (file_exists($path)) {
            unlink($path);
        }
    
        if (!file_put_contents($path, $imageData)) {
            print_r("Failed to save image to path: $path");
            return '';
        }
    
        // Get image dimensions
        list($width, $height) = getimagesize($path);
    
        // Create the JSON structure
        $relativePath = 'images/' . $filename;
        $joomlaImage = $relativePath . '#joomlaImage://local-' . $relativePath . "?width={$width}&height={$height}";
        $result = [
            'imagefile' => $joomlaImage,
            'alt_text' => '' // Adjust or set dynamically if needed
        ];
    
        return json_encode($result);
    }
    


    /**
     * Generates HTML list of links from data array.
     *
     * @param array $dataArray
     * @param string $displayKey
     * @param string $idKey
     * @return string
     */
    private function generateListHtml($dataArray, $displayKey, $idKey)
    {
        $html = '<ul>';
        foreach ($dataArray as $data) {
            $link = Uri::root() . 'index.php?option=com_content&view=article&id=' . $data[$idKey];
            $html .= '<li><a href="' . $link . '">' . htmlspecialchars($data[$displayKey]) . '</a></li>';
        }
        $html .= '</ul>';
        return $html;
    }

    /**
     * Generates HTML list of links from data array for research outputs.
     *
     * @param int $fieldId
     * @param int $articleId
     * @param string $value
     */
    private function generateListHtmlResearchOutputs($dataArray, $displayKey, $idKey)
    {
        $html = '<ul>';
        foreach ($dataArray as $data) {
            $link = $data['pure-link'];
            $html .= '<li><a href="' . $link . '">' . htmlspecialchars($data[$displayKey]) . '</a></li>';
        }
        $html .= '</ul>';
        return $html;
    }

    /**
     * Generates title and alias for articles.
     *
     * @param string $name
     * @param string $uuid
     * @return array
     */
    private function generateTitle($name, $uuid)
    {
        $name_clean = trim(preg_replace('/(\[.+?\]|\((ulht|ulp)\))/i', '', $name));
        $name = trim(preg_replace('/\(.+?\)/', '', $name_clean));
        $slug = $this->generateSlug($name, $uuid);

        return ['title' => $name, 'alias' => $slug];
    }

    /**
     * Generates a slug for articles.
     *
     * @param string $name
     * @param string $uuid
     * @return string
     */
    private function generateSlug($name, $uuid)
    {
        $slug = $name . '-' . $uuid;
        return JFilterOutput::stringURLSafe($slug);
    }

    /*-------------------------------------------DATABASE INTERACTIONS-----------------------------------------------------------*/

    /**
     * Retrieves an article ID based on person UUID.
     *
     * @param string $personUuid
     * @return int
     */
    public function getArticleViaPersonUuid($personUuid)
    {
        return $this->getArticleViaUuid($personUuid);
    }

    /**
     * Retrieves an article ID based on institution UUID.
     *
     * @param string $institutionUuid
     * @return int
     */
    public function getArticleViaInstitutionUuid($institutionUuid)
    {
        return $this->getArticleViaUuid($institutionUuid);
    }

    /**
     * Retrieves an article ID based on research output UUID.
     *
     * @param string $researchOutputUuid
     * @return int
     */
    public function getArticleViaResearchOutputUuid($researchOutputUuid)
    {
        return $this->getArticleViaUuid($researchOutputUuid);
    }

    /**
     * Generic method to retrieve article ID based on UUID.
     *
     * @param string $uuid
     * @return int|null
     */
    private function getArticleViaUuid($uuid)
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('item_id'))
            ->from($db->quoteName('#__fields_values'))
            ->where($db->quoteName('value') . ' = ' . $db->quote($uuid));
        $db->setQuery($query);
        return $db->loadResult();
    }

    /**
     * Retrieves an article ID based on alias.
     *
     * @param string $alias
     * @return int
     */
    public function getArticleViaAlias($alias)
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('alias') . ' = ' . $db->quote($alias));
        $db->setQuery($query);
        return $db->loadResult();
    }

    /**
     * Deletes an article by ID and associated data.
     *
     * @param int $articleId
     * @return string|null
     */
    public function deleteArticleById($articleId)
    {
        $db = Factory::getDbo();

        try {
            $db->transactionStart();

            // Delete custom field values associated with the article
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__fields_values'))
                ->where($db->quoteName('item_id') . ' = ' . (int)$articleId);
            $db->setQuery($query);
            $db->execute();

            // Get asset_id for the article
            $query = $db->getQuery(true)
                ->select($db->quoteName('asset_id'))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = ' . (int)$articleId);
            $db->setQuery($query);
            $assetId = (int)$db->loadResult();

            // Delete the asset entry if it exists
            if ($assetId) {
                $query = $db->getQuery(true)
                    ->delete($db->quoteName('#__assets'))
                    ->where($db->quoteName('id') . ' = ' . $assetId);
                $db->setQuery($query);
                $db->execute();
            }

            // Get the articleText before deleting the article
            $query = $db->getQuery(true)
                ->select($db->quoteName('introtext'))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = ' . (int)$articleId);
            $db->setQuery($query);
            $articleText = $db->loadResult();

            // Finally, delete the article
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = ' . (int)$articleId);
            $db->setQuery($query);
            $db->execute();

            $db->transactionCommit();
            return $articleText;

        } catch (Exception $e) {
            $db->transactionRollback();
            print_r('Failed to delete article: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Updates an article with a custom field value.
     *
     * @param int $fieldId
     * @param int $articleId
     * @param string $value
     */
    private function updateArticleWithCustomField($fieldId, $articleId, $value)
    {
        $db = Factory::getDbo();

        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName('value'))
                ->from($db->quoteName('#__fields_values'))
                ->where([
                    $db->quoteName('field_id') . ' = ' . $db->quote($fieldId),
                    $db->quoteName('item_id') . ' = ' . $db->quote($articleId)
                ]);

            $db->setQuery($query);
            $exists = $db->loadResult();

            if ($exists) {
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__fields_values'))
                    ->set($db->quoteName('value') . ' = ' . $db->quote($value))
                    ->where([
                        $db->quoteName('field_id') . ' = ' . $db->quote($fieldId),
                        $db->quoteName('item_id') . ' = ' . $db->quote($articleId)
                    ]);
            } else {
                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__fields_values'))
                    ->columns($db->quoteName(['field_id', 'item_id', 'value']))
                    ->values(implode(',', [$db->quote($fieldId), $db->quote($articleId), $db->quote($value)]));
            }

            $db->setQuery($query);
            $db->execute();

        } catch (Exception $e) {
            print_r('Error updating article with custom field: ' . $e->getMessage());
        }
    }

    /*-------------------------------------------API CALLS-------------------------------------------------------------*/

    /**
     * Makes a POST request to Pure API.
     *
     * @param string $endpoint
     * @param array $data
     * @return array|null
     */
    private function post($endpoint, $data)
    {
        return $this->makeApiRequest('POST', $endpoint, $data);
    }

    /**
     * Makes a GET request to Pure API.
     *
     * @param string $endpoint
     * @param array $queryParams
     * @return array|null
     */
    private function getCustom($endpoint, $queryParams = [])
    {
        return $this->makeApiRequest('GET', $endpoint, $queryParams);
    }

    /**
     * Makes a generic API request to Pure API.
     *
     * @param string $method
     * @param string $endpoint
     * @param array $params
     * @return array|null
     */
    private function makeApiRequest($method, $endpoint, $params)
    {
        $url = $this->base_url . $endpoint;
        $http = JHttpFactory::getHttp();
        $api_key = '47e5bd25-8649-4b60-b8b7-ac0e1381b300';
        $headers = ['Content-Type' => 'application/json', 'Api-Key' => $api_key];

        try {
            $response = $method === 'POST'
                ? $http->post($url, json_encode($params), $headers)
                : $http->get($url . '?' . http_build_query($params), $headers);

				if ($response->code === 404) {
					print_r('API request failed with 404: ' . $url);
					return null;
				}
		
				if ($response->code >= 200 && $response->code < 300) {
					return json_decode($response->body, true);
				} else {
					throw new Exception('API request failed with response code: ' . $response->code);
				}
        } catch (Exception $e) {
            print_r('API request error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetches all institutions from Pure API.
     *
     * @return array|null
     */
    private function institutions()
    {
        return $this->getCustom('organizations');
    }

    /**
     * Fetches dependents filtered by person from Pure API.
     *
     * @param string $uuid
     * @return array|null
     */
    private function institutionDependentsFilteredByPerson($uuid)
    {
        return $this->getCustom('organizations/' . $uuid . '/dependents');
    }

    /**
     * Fetches person details from Pure API.
     *
     * @param string $uuid
     * @return array|null
     */
    private function person($uuid)
    {
        return $this->getCustom('persons/' . $uuid);
    }

    /**
     * Fetches user details from Pure API.
     *
     * @param string $uuid
     * @return array|null
     */
    private function user($uuid)
    {
        return $this->getCustom('users/' . $uuid);
    }

    /**
     * Fetches institution details from Pure API.
     *
     * @param string $uuid
     * @return array|null
     */
    public function getInstitutionDetails($uuid)
    {
        return $this->getCustom('organizations/' . $uuid);
    }

    /**
     * Fetches research output details from Pure API.
     *
     * @param string $uuid
     * @return array|null
     */
    public function researchOutput($uuid)
    {
        return $this->getCustom('research-outputs/' . $uuid);
    }

}
