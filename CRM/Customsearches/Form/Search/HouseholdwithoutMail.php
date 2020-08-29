<?php
use CRM_Customsearches_ExtensionUtil as E;

/**
 * A custom contact search
 */
class CRM_Customsearches_Form_Search_HouseholdwithoutMail extends CRM_Contact_Form_Search_Custom_Base implements CRM_Contact_Form_Search_Interface {
  function __construct(&$formValues) {
    parent::__construct($formValues);
  }

  /**
   * Prepare a set of search fields
   *
   * @param CRM_Core_Form $form modifiable
   * @return void
   */
  function buildForm(&$form) {
    CRM_Utils_System::setTitle(E::ts('Liste des Foyers dont les Chefs de Famille n\'ont pas d\'adresses mail'));

// Affichage des groupes à choisir
	$groups =  CRM_Core_PseudoConstant::nestedGroup();
	
	$select2style = array(
			'multiple' => TRUE,
			'style' => 'width: 100%; max-width: 60em;',
			'class' => 'crm-select2',
			'placeholder' => ts('- select -'),
		);
		
	$form->add('select', 'group',
			ts('Include Group(s)'),
			$groups,
			FALSE,
			$select2style
		);
    // Optionally define default search values	
	$form->setDefaults(array(
      'household_name' => '',
	  'group' => NULL,
      ));

    /**
     * if you are using the standard template, this array tells the template what elements
     * are part of the search criteria
     */
	 
	$form->assign('elements', array('household_name','group'));

  }

  /**
   * Get a list of summary data points
   *
   * @return mixed; NULL or array with keys:
   *  - summary: string
   *  - total: numeric
   */
  function summary() {
    return NULL;
    // return array(
    //   'summary' => 'This is a summary',
    //   'total' => 50.0,
    // );
  }

  /**
   * Get a list of displayable columns
   *
   * @return array, keys are printable column headers and values are SQL column names
   */
  function &columns() {
    // return by reference
    $columns = array(
      E::ts('Contact Id') => 'contact_id',
      E::ts('Display Name') => 'display_name',
    );
    return $columns;
  }

  /**
   * Construct a full SQL query which returns one page worth of results
   *
   * @param int $offset
   * @param int $rowcount
   * @param null $sort
   * @param bool $includeContactIDs
   * @param bool $justIDs
   * @return string, sql
   */
  function all($offset = 0, $rowcount = 0, $sort = NULL, $includeContactIDs = FALSE, $justIDs = FALSE) {
    // delegate to $this->sql(), $this->select(), $this->from(), $this->where(), etc.
    return $this->sql($this->select(), $offset, $rowcount, $sort, $includeContactIDs, NULL);
  }

  /**
   * Construct a SQL SELECT clause
   *
   * @return string, sql fragment with SELECT arguments
   */
  function select() {
    return "
      contact_a.id           as contact_id  ,
      contact_a.display_name as display_name
    ";
  }

  /**
   * Construct a SQL FROM clause
   *
   * @return string, sql fragment with FROM and JOIN clauses
   */
  function from() {
    return "
		FROM civicrm_contact as contact_a
		LEFT OUTER JOIN civicrm_relationship r_h ON (r_h.contact_id_b =  contact_a.id AND r_h.relationship_type_id = 7)
		LEFT OUTER JOIN civicrm_contact chef_h ON chef_h.id = r_h.contact_id_a
		LEFT OUTER JOIN civicrm_membership m_h ON chef_h.id = m_h.contact_id
    ";
  }

  /**
   * Construct a SQL WHERE clause
   *
   * @param bool $includeContactIDs
   * @return string, sql fragment with conditional expressions
   */
   
 /*
 TO DO Changer les paramètres en Groupes
 Voir page : https://wiki.civicrm.org/confluence/display/CRMDOC44/Custom+Search+Find+Households+by+group
 */
   
  function where($includeContactIDs = FALSE) {
    $params = array();
    $where = "
		NOT EXISTS (
			SELECT 
			*
			FROM
			civicrm_contact c
			LEFT OUTER JOIN civicrm_relationship r ON (r.contact_id_b = c.id AND r.relationship_type_id = 7)
			LEFT OUTER JOIN civicrm_contact chef ON chef.id = r.contact_id_a 
			LEFT OUTER JOIN civicrm_membership m ON chef.id = m.contact_id
			WHERE EXISTS (
				SELECT * FROM civicrm_email e WHERE e.contact_id = chef.id
			)
			AND chef.is_deleted  = 0
			AND chef.is_deceased = 0
			AND c.is_deleted = 0
			AND c.contact_type = 'Household'
			AND c.id = contact_a.id
			AND m.membership_type_id IN  ('1', '2', '3')
			AND m.status_id IN  ('1', '8', '2', '3')
		)
		AND contact_a.contact_type = 'Household'
		AND chef_h.is_deleted = 0
		AND chef_h.is_deceased = 0
		AND contact_a.is_deleted = 0
		AND m_h.membership_type_id IN ('1', '2', '3')
		AND m_h.status_id IN  ('1', '8', '2', '3')
		";

    $count  = 1;
    $clause = array();
    $name   = CRM_Utils_Array::value('household_name',
      $this->_formValues
    );
    if ($name != NULL) {
      if (strpos($name, '%') === FALSE) {
        $name = "%{$name}%";
      }
      $params[$count] = array($name, 'String');
      $clause[] = "contact_a.household_name LIKE %{$count}";
      $count++;
    }

    $state = CRM_Utils_Array::value('state_province_id',
      $this->_formValues
    );
    if (!$state &&
      $this->_stateID
    ) {
      $state = $this->_stateID;
    }

    if ($state) {
      $params[$count] = array($state, 'Integer');
      $clause[] = "state_province.id = %{$count}";
    }

    if (!empty($clause)) {
      $where .= ' AND ' . implode(' AND ', $clause);
    }

    return $this->whereClause($where, $params);
  }

  /**
   * Determine the Smarty template for the search screen
   *
   * @return string, template path (findable through Smarty template path)
   */
  function templateFile() {
    return 'CRM/Contact/Form/Search/Custom.tpl';
  }

  /**
   * Modify the content of each row
   *
   * @param array $row modifiable SQL result row
   * @return void
   */
  function alterRow(&$row) {
    $row['sort_name'] .= ' ( altered )';
  }
}
