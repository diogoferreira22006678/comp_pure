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
    protected $personArray = [];
    protected $researchOutputArray = [];

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
            $this->institutionFilteredTypePersonRoute($params['institution'], $params['type']);
            $this->researchOutputsFilteredTypeRoute($params['institution']);
            $this->createIndexPage($params);
        } catch (Exception $e) {
            print_r('Error in callApiPure: ' . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Creates index page for the institution.
     *
     * @param array $params
     */
    public function createIndexPage($params)
    {
        $institutionDetails = $this->getInstitutionDetails($params['institution']);
        $this->createArticleIndex($institutionDetails, $this->ensureCategoryExists($this->institutionArray['institution']));
    }

    /**
     * Fetches persons of specified types from an institution.
     *
     * @param string $institution
     * @param array $types
     */
    public function institutionFilteredTypePersonRoute($institution, $types)
    {
        $response = $this->institutionDependentsFilteredByPerson($institution);
        $persons = $response['items'];
        $data = [];

        foreach ($persons as $person) {
            if ($person['systemName'] !== 'Person') continue;

            $person_uuid = $person['uuid'];
            $person_response = $this->person($person_uuid);
            $person_type = $person_response['staffOrganizationAssociations'][0]['staffType']['term']['en_GB'] ?? '';

            if ($types && !in_array($person_type, $types)) continue;

            $profile_photo = $this->getProfilePhoto($person_response['profilePhotos']);
            $name_variant = $this->getNameVariant($person_response['name']);
            $email = $this->getUserEmail($person_uuid);
            $orcid_id = $person_response['orcid'] ?? '';
            [$scopus_author_id, $ciencia_vitae_id] = $this->getIdentifiers($person_response['identifiers']);
            $biography = $person_response['profileInformation'][0]['value']['pt_PT'] ?? '';
            $pure_link = $person_response['portalUrl'] ?? '';

            $data[] = [
                'uuid' => $person_uuid,
                'profile_photo' => $profile_photo,
                'name_variant' => $name_variant,
                'email' => $email,
                'orcid_id' => $orcid_id,
                'ciencia_vitae_id' => $ciencia_vitae_id,
                'scopus_author_id' => $scopus_author_id,
                'biography' => $biography,
                'pure_link' => $pure_link
            ];
        }

        $this->processPersonsData($data);
    }

    /**
     * Fetches and processes research outputs for an institution.
     *
     * @param string $institution
     */
    public function researchOutputsFilteredTypeRoute($institution)
    {
        $response = $this->institutionDependentsFilteredByPerson($institution);
        $research_outputs = $response['items'];

        foreach ($research_outputs as $research_output) {
            if ($research_output['systemName'] !== 'ResearchOutput') continue;

            $research_output_uuid = $research_output['uuid'];
            $research_output_response = $this->researchOutput($research_output_uuid);

            $formattedData = $this->formatResearchOutputData($research_output_response);
        
            $this->researchOutputArray[] = $formattedData;
            //$this->createArticleResearchOutput($formattedData, $this->ensureCategoryExists($this->categoryResearchOutput['researchOutput']));
        }
    }

    /**
     * Formats research output data for article creation.
     *
     * @param array $research_output_response
     * @return array
     */
    private function formatResearchOutputData($research_output_response)
    {
        $title = trim($research_output_response['title']['value'] ?? 'No Title Available');
        $publication_date = $research_output_response['publicationStatuses'][0]['publicationDate']['year'] ?? 'Unknown';
        $abstract = $research_output_response['abstract']['pt_PT'] ?? 'No abstract available';
        $pure_link = $research_output_response['portalUrl'] ?? '';
        $contributors = $this->getContributors($research_output_response['contributors']);
        $keywords = $this->getKeywords($research_output_response['keywordGroups']);

        return [
            'uuid' => $research_output_response['uuid'],
            'title' => $title,
            'publication-date' => $publication_date,
            'abstract' => $abstract,
            'pure-link' => $pure_link,
            'contributors' => $contributors,
            'keywords' => $keywords
        ];
    }

    /**
     * Process and create articles for persons data.
     *
     * @param array $data
     */
    private function processPersonsData($data)
    {
        $categoriesIds = $this->getCategoriesIds();
        $this->ensureGroupFields();

        foreach ($data as $person) {
            $categoryId = $this->getPersonCategoryId($person['name_variant'], $categoriesIds);
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

        return $categoriesIds[$mapping[$person_type] ?? 'other'];
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
            if ($profilePhoto['type'] === 'portrait') {
                return $profilePhoto['url'];
            }
        }
        return '';
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
     * Retrieves contributors list from contributors array.
     *
     * @param array $contributorsArray
     * @return array
     */
    private function getContributors($contributorsArray)
    {
        $contributors = [];
        foreach ($contributorsArray as $contributor) {
            $firstName = $contributor['name']['firstName'] ?? '';
            $lastName = $contributor['name']['lastName'] ?? '';
            if ($firstName || $lastName) {
                $contributors[] = $firstName . ' ' . $lastName;
            }
        }
        return $contributors;
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
        return $this->ensureGroupField($groupName, 'com_content.article', $this->categories, $onlyCheck);
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
        return $this->ensureGroupField($groupName, 'com_content.article', $this->institutionArray, $onlyCheck);
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
        return $this->ensureGroupField($groupName, 'com_content.article', $this->categoryResearchOutput, $onlyCheck);
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
    private function ensureGroupField($groupName, $context, $categories, $onlyCheck = false)
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

        $this->ensureFieldsExist($groupId, $categories);
        return $groupId;
    }

    /**
     * Ensures required fields exist in a group.
     *
     * @param int $groupId
     * @param array $categories
     */
    private function ensureFieldsExist($groupId, $categories)
    {
        $fields = $this->getFieldDefinitions();

        foreach ($fields as $fieldInfo) {
            $this->createOrUpdateField($groupId, $fieldInfo, $categories);
        }
    }

    /**
     * Provides field definitions for creation.
     *
     * @return array
     */
    private function getFieldDefinitions()
    {
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
		$db = Factory::getDbo();
		$query = $db->getQuery(true);
		$query->select('id')
			->from($db->quoteName('#__fields'))
			->where($db->quoteName('name') . ' = ' . $db->quote($fieldInfo['name']))
			->where($db->quoteName('context') . ' = ' . $db->quote('com_content.article'))
			->where($db->quoteName('group_id') . ' = ' . $db->quote($groupId));
		$db->setQuery($query);
		$existingFieldId = $db->loadResult();
	
		if ($existingFieldId) {
			print_r('Field already exists: ' . $fieldInfo['name']);
			$this->associateFieldWithCategories($existingFieldId, $categories);
			return;
		}

        $this->createField($groupId, $fieldInfo, $categories);
       
    }

    /**
     * Creates a field and associates it with categories.
     *
     * @param int $groupId
     * @param array $fieldInfo
     * @param array $categories
     */
    private function createField($groupId, $fieldInfo, $categories)
    {
        \JLoader::register('FieldsModelField', JPATH_ADMINISTRATOR . '/components/com_fields/models/field.php');
        $app = Factory::getApplication();

        $params = ['show_label' => '1', 'required' => '0', 'language' => ''];
        if (isset($fieldInfo['filter']) && $fieldInfo['filter'] === 'html') {
            $fieldparams = ['filter' => 'safehtml', 'maxlength' => ''];
        }

        $data = [
            'title' => $fieldInfo['title'],
            'name' => $fieldInfo['name'],
            'label' => $fieldInfo['title'],
            'type' => 'textarea',
            'context' => 'com_content.article',
            'group_id' => $groupId,
            'state' => 1,
            'access' => 1,
            'language' => '*',
            'created_by' => Factory::getUser()->id,
            'params' => json_encode($params),
            'fieldparams' => json_encode($fieldparams ?? []),
            'description' => $fieldInfo['title'] . ' field',
        ];

        $fieldModel = $app->bootComponent('com_fields')->getMVCFactory()->createModel('Field', 'Administrator', ['ignore_request' => true]);
        if (!$fieldModel->save($data)) {
            print_r('Error creating field: ' . $fieldModel->getError());
            return;
        }

        $fieldId = $fieldModel->getState('field.id');
        $this->associateFieldWithCategories($fieldId, $categories);
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
            $info = $this->generateTitle($person['name_variant'], $person['uuid']);
            $assetId = $this->getArticleViaAlias($info['alias']);
            $introtext = $assetId ? $this->deleteArticleById($assetId) : 'Default introtext';

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
    public function createArticleResearchOutput($researchOutput, $categoryId)
    {
        $app = Factory::getApplication();

        try {
            $info = $this->generateTitle($researchOutput['title'], $researchOutput['uuid']);
            $assetId = $this->getArticleViaAlias($info['alias']);
            $introtext = $assetId ? $this->deleteArticleById($assetId) : 'Default introtext';

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

            $groupFieldId = $this->ensureGroupFieldForResearchOutput('Research Output', true);
            $fields = $this->getFieldsByGroup($groupFieldId);

            $model = $app->bootComponent('com_content')->getMVCFactory()->createModel('Article', 'Administrator', ['ignore_request' => true]);
            if (!$model->save($data)) {
                throw new Exception('Failed to save article: ' . $model->getError() . ' - with name: ' . $info['title']);
            }

            $articleId = $model->getState('article.id');
            $researchOutput['article_id'] = $articleId;
            $this->researchOutputArray[] = $researchOutput;

            $this->updateFieldsForArticle($fields, $researchOutput, $articleId);

        } catch (Exception $e) {
            print_r('Failed to create research output article: ' . $e->getMessage());
        }
    }

    /**
     * Creates or updates an index article for an institution.
     *
     * @param array $institutionDetails
     * @param int $categoryId
     */
    public function createArticleIndex($institutionDetails, $categoryId)
    {
        $app = Factory::getApplication();

        try {

            $academics = $this->generateListHtml($this->personArray, 'name_variant', 'article_id');
            $research_outputs = $this->generateListHtmlResearchOutputs($this->researchOutputArray, 'title', 'article_id');

            $info = $this->generateTitle($institutionDetails['name']['pt_PT'], $institutionDetails['uuid']);
            $assetId = $this->getArticleViaAlias($info['alias']);
            $introtext = $assetId ? $this->deleteArticleById($assetId) : $academics . '<br><br>' . $research_outputs;

            $data = [
                'title' => $info['title'],
                'alias' => $info['alias'],
                'introtext' => $introtext,
                'catid' => $categoryId,
                'state' => 1,
                'language' => '*',
                'access' => 1,
                'created_by' => Factory::getUser()->id,
                'featured' => 1
            ];

            $groupFieldId = $this->ensureGroupFieldForInstitution('Institution', true);
            $fields = $this->getFieldsByGroup($groupFieldId);

            $model = $app->bootComponent('com_content')->getMVCFactory()->createModel('Article', 'Administrator', ['ignore_request' => true]);
            if (!$model->save($data)) {
                throw new Exception('Failed to save article: ' . $model->getError() . ' - with name: ' . $info['title']);
            }

            $articleId = $model->getState('article.id');
            $this->updateFieldsForArticle($fields, [
                'uuid' => $institutionDetails['uuid'],
                'name-institution' => $institutionDetails['name']['pt_PT'],
                'academics' => $academics,
                'research-outputs-institution' => $research_outputs
            ], $articleId);

        } catch (Exception $e) {
            print_r('Failed to create index article: ' . $e->getMessage());
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
                $this->updateArticleWithCustomField($field['id'], $articleId, $fieldValue);
            }
        }
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
