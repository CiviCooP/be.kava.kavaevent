<?php

class KavaEventHelper {

  public $contactID = 0;
  public $eventID = 0;
  public $eventTitle = '';
  public $maxRegistrations = 0;
  public $pharmacyID = 0;
  public $canRegisterTeamMembers = FALSE;
  public $teamMembers = [];

  public function __construct($drupalID, $eventID) {
    if ($eventID > 0 && $drupalID > 0) {
      civicrm_initialize();

      $this->eventID = $eventID;
      $this->LookupContact($drupalID);

      $this->eventID = $eventID;
      $this->LookupPermissions();

      if ($this->canRegisterTeamMembers) {
        $this->getTeamMembers();
      }
    } else {
      throw new Exception('U moet eerst aanmelden');
    }
  }

  private function LookupPermissions() {

    // Check event settings

    // NOTE: These events fields are currently not created automatically
    $isActiveFieldName = $this->getCustomFieldApiName('Teaminschrijving', 'Teaminschrijving_actief');
    $maxRegistrationsFieldName = $this->getCustomFieldApiName('Teaminschrijving', 'Maximum_aantal');

    $eventInfo = civicrm_api3('Event', 'getsingle', [
      'id'     => $this->eventID,
      'return' => $isActiveFieldName . ',' . $maxRegistrationsFieldName,
    ]);

    if (!isset($eventInfo[$isActiveFieldName]) || $eventInfo[$isActiveFieldName] == 0) {
      return;
    }

    if (isset($eventInfo[$maxRegistrationsFieldName])) {
      $this->maxRegistrations = $eventInfo[$maxRegistrationsFieldName];
    }

    // Teaminschrijving is active for this event, continue...

    $relTypeTitularis = $this->getRelationshipTypeId('heeft als titularis');
    $relTypeCoTitularis = $this->getRelationshipTypeId('heeft als co-titularis');

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
    $sqlParams = [
      1 => [$this->contactID, 'Integer'],
      2 => [$relTypeTitularis, 'Integer'],
      3 => [$relTypeCoTitularis, 'Integer'],
    ];
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

      // store the title
      $this->eventTitle = $event['title'];

      if ($event['custom_' . $fieldActive['id']] == 1) {
        $this->canRegisterTeamMembers = TRUE;

        $this->maxRegistrations = $event['custom_' . $fieldMax['id']];
        // make sure the value is filled in
        if (!$this->maxRegistrations) {
          $this->maxRegistrations = 1000;
        }
      }
      else {
        $this->canRegisterTeamMembers = FALSE;
      }
    }
  }

  private function LookupContact($drupalID) {
    // get the civi id for this drupal id
    $params = [
      'sequential' => 1,
      'uf_id'      => $drupalID,
    ];
    $result = civicrm_api3('UFMatch', 'get', $params);
    if ($result['is_error'] == 0 && $result['count'] > 0) {
      // ok, found contact
      $this->contactID = $result['values'][0]['contact_id'];
    } else {
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
    $sqlParams = [
      1 => [$this->pharmacyID, 'Integer'],
      2 => [$relTypeTitularis, 'Integer'],
      3 => [$relTypeCoTitularis, 'Integer'],
      4 => [$relTypeAdjunct, 'Integer'],
      5 => [$relTypeAssistent, 'Integer'],
      6 => [$relTypeOwner, 'Integer'],
      7 => [$relTypeCoOwner, 'Integer'],
    ];
    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);

    while ($dao->fetch()) {
      $this->teamMembers[] = [
        $dao->id,
        $dao->display_name,
        $dao->job_title,
      ];
    }
  }

  public function registerContactsForEvent($contactIDs) {
    foreach ($contactIDs as $contactID) {
      // Check if registration already exists
      $count = civicrm_api3('Participant', 'getcount', [
        'contact_id' => $contactID,
        'event_id'   => $this->eventID,
      ]);
      if ($count > 0) {
        continue;
      }

      // Add new registration
      civicrm_api3('Participant', 'create', [
        'contact_id'    => $contactID,
        'event_id'      => $this->eventID,
        'status_id'     => 'Registered',
        'role_id'       => 'Attendee',
        'register_date' => date('Ymdhis'),
        'source'        => 'Teaminschrijving website',
      ]);
    }
  }

  private function getCustomFieldApiName($customGroupName, $customFieldName) {
    try {
      $fieldId = civicrm_api3('CustomField', 'getvalue', [
        'return'          => 'id',
        'custom_group_id' => $customGroupName,
        'name'            => $customFieldName,
      ]);
      return 'custom_' . $fieldId;
    } catch (CiviCRM_API3_Exception $e) {
      return NULL;
    }
  }

  private function getRelationshipTypeId($nameAB) {
    try {
      return civicrm_api3('RelationshipType', 'getvalue', [
        'return'   => 'id',
        'name_a_b' => $nameAB,
      ]);
    } catch (CiviCRM_API3_Exception $e) {
      return NULL;
    }
  }
}
