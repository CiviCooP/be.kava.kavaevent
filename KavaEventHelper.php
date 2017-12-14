<?php

class KavaEventHelper {
  public $contactID = 0;
  public $eventID = 0;
  public $maxRegistrations = 0;
  public $pharmacyID = 0;
  public $canRegisterTeamMembers = FALSE;
  public $teamMembers = array();

  public function __construct($drupalID, $eventID) {
    if ($drupalID > 0) {
      civicrm_initialize();

      $this->eventID = $eventID;
      $this->LookupContact($drupalID);
      $this->LookupPermissions($eventID);

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
      $this->pharmacyID = $id;

      // check if multiple registrations are allowed for this event (= stored in custom fields)
      $params = array(
        'sequential' => 1,
        'custom_group_id' => "Teaminschrijving",
        'name' => 'Teaminschrijving_actief',

      );
      $fieldActive = civicrm_api3('CustomField', 'getsingle', $params);

      $params = array(
        'sequential' => 1,
        'custom_group_id' => "Teaminschrijving",
        'name' => 'Maximum_aantal',
      );
      $fieldMax = civicrm_api3('CustomField', 'getsingle', $params);

      $params = array(
        'event_id' => $this->eventID,
        'return' => 'id,title,custom_' . $fieldActive['id'] . ',custom_' . $fieldMax['id'],
      );
      $event = civicrm_api3('Event', 'getsingle', $params);

      if ($event['custom_' . $fieldActive['id']]) {
        $this->canRegisterTeamMembers = TRUE;
        $this->maxRegistrations = $event['custom_' . $fieldMax['id']];
      }
      else {
        $this->canRegisterTeamMembers = FALSE;
      }
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
    $sql = "
      SELECT * FROM
      (
        SELECT
          c.id
          , c.display_name
          , c.sort_name
          , (case r.relationship_type_id
               when %2 then 'Titularis'
               when %3 then 'Co-titularis'
               when %4 then 'Adjunct'
               when %5 then 'Assistent'
               when %6 then 'Eigenaar'
               when %7 then 'Mede-eigenaar'
            end) job_title
        FROM
          civicrm_contact c
        INNER JOIN
          civicrm_relationship r on c.id = r.contact_id_b
        WHERE
          r.contact_id_a = %1
          AND r.relationship_type_id in (%2, %3)
          AND r.is_active = 1
        UNION ALL
        SELECT
          c.id
          , c.display_name
          , c.sort_name
          , (case r.relationship_type_id
               when %2 then 'Titularis'
               when %3 then 'Co-titularis'
               when %4 then 'Adjunct'
               when %5 then 'Assistent'
               when %6 then 'Eigenaar'
               when %7 then 'Mede-eigenaar'
            end) job_title
        FROM
          civicrm_contact c
        INNER JOIN
          civicrm_relationship r on c.id = r.contact_id_a
        WHERE
          r.contact_id_b = %1
          AND r.relationship_type_id in (%4, %5, %6, %7)
          AND r.is_active = 1      
      ) dummy  
      ORDER BY
        sort_name
    ";
    $sqlParams = array(
      1 => array($this->pharmacyID, 'Integer'),
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
        $dao->job_title
      );
    }
  }

  public function registerContactsForEvent($eventID, $contactIDs) {
  }
}