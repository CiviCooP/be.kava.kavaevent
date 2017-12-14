<?php

class KavaEventHelper {
  public $contactID = 0;
  public $pharmacyID = 0;
  public $canRegisterTeamMembers = FALSE;
  public $teamMembers = array();

  public function __construct($drupalID) {
    if ($drupalID > 0) {
      civicrm_initialize();

      $this->LookupContact($drupalID);
      $this->LookupPermissions();

      if ($this->canRegisterTeamMembers) {
        $this->getTeamMembers();
      }
    }
    else {
      throw new Exception('U moet eerst aanmelden');
    }
  }

  private function LookupPermissions() {
    $relTypeTitularis = 35;
    $relTypeCoTitularis = 41;

    // check if this contact is titularis or co-titularis
    $sql = '
      SELECT
        max(r.contact_id_a)
      FROM
        civicrm_relationship r
      WHERE
        r.contact_id_b = %1
        AND r.relationship_type_id in (%2, %3)
        and r.is_active = 1
    ';
    $sqlParams = array(
      1 => array($this->contactID, 'Integer'),
      2 => array($relTypeTitularis, 'Integer'),
      3 => array($relTypeCoTitularis, 'Integer'),
    );
    $id = CRM_Core_DAO::singleValueQuery($sql, $sqlParams);

    if ($id) {
      $this->canRegisterTeamMembers = TRUE;
      $this->pharmacyID = $id;
    }
  }

  private function LookupContact($drupalID) {
    // get the civi id for this drupal id
    $params = array(
      'sequential' => 1,
      'uf_id' => $drupalID,
    );
    $result = civicrm_api3('UFMatch', 'get', $params);
    if ($result['is_error'] == 0 && $result['count'] > 0) {
      // ok, found contact
      $this->contactID = $result['values'][0]['contact_id'];
    }
    else {
      // not found
      throw new Exception('Probleem tijdens het ophalen van uw gegevens. Neem contact op met KAVA.');
    }
  }

  private function getTeamMembers() {
    $relTypeTitularis = 35;
    $relTypeCoTitularis = 41;
    $relTypeAdjunct = 37;
    $relTypeAssistent = 53;
    $relTypeOwner = 56;
    $relTypeCoOwner = 54;

    // lookup colleagues
    $sql = '
      SELECT
        c.id
        , c.display_name
        , c.sort_name
      FROM
        civicrm_contact c
      INNER JOIN
        civicrm_relationship r on c.id = r.contact_id_a
      WHERE
        r.contact_id_b = %1
        AND r.relationship_type_id in (%2)
        AND r.is_active = 1
      UNION ALL
      SELECT
        c.id
        , c.display_name
        , c.sort_name
      FROM
        civicrm_contact c
      INNER JOIN
        civicrm_relationship r on c.id = r.contact_id_b
      WHERE
        r.contact_id_a = %1
        AND r.relationship_type_id in (%3, %4, %5, %6, %7)
        AND r.is_active = 1      
      ORDER BY
        sort_name
    ';
    $sqlParams = array(
      1 => array($this->contactID, 'Integer'),
      2 => array($relTypeTitularis, 'Integer'),
      3 => array($relTypeCoTitularis, 'Integer'),
      4 => array($relTypeAdjunct, 'Integer'),
      5 => array($relTypeAssistent, 'Integer'),
      6 => array($relTypeOwner, 'Integer'),
      7 => array($relTypeCoOwner, 'Integer'),
    );
    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);

    while ($dao->fetch()) {
      $this->teamMembers[] = array(
        $dao->id,
        $dao->display_name,
      );
    }
  }
}