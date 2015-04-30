<?php

final class PhabricatorCalendarEventEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorCalendarApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Calendar');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorCalendarEventTransaction::TYPE_NAME;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_START_DATE;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_END_DATE;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_STATUS;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_DESCRIPTION;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_CANCEL;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_INVITE;

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorCalendarEventTransaction::TYPE_NAME:
        return $object->getName();
      case PhabricatorCalendarEventTransaction::TYPE_START_DATE:
        return $object->getDateFrom();
      case PhabricatorCalendarEventTransaction::TYPE_END_DATE:
        return $object->getDateTo();
      case PhabricatorCalendarEventTransaction::TYPE_STATUS:
        $status = $object->getStatus();
        if ($status === null) {
          return null;
        }
        return (int)$status;
      case PhabricatorCalendarEventTransaction::TYPE_DESCRIPTION:
        return $object->getDescription();
      case PhabricatorCalendarEventTransaction::TYPE_CANCEL:
        return $object->getIsCancelled();
      case PhabricatorCalendarEventTransaction::TYPE_INVITE:
        $map = $xaction->getNewValue();
        $phids = array_keys($map);
        $invitees = array();

        if ($map && !$this->getIsNewObject()) {
          $invitees = id(new PhabricatorCalendarEventInviteeQuery())
            ->setViewer($this->getActor())
            ->withEventPHIDs(array($object->getPHID()))
            ->withInviteePHIDs($phids)
            ->execute();
          $invitees = mpull($invitees, null, 'getInviteePHID');
        }

        $old = array();
        foreach ($phids as $phid) {
          $invitee = idx($invitees, $phid);
          if ($invitee) {
            $old[$phid] = $invitee->getStatus();
          } else {
            $old[$phid] = PhabricatorCalendarEventInvitee::STATUS_UNINVITED;
          }
        }
        return $old;
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorCalendarEventTransaction::TYPE_NAME:
      case PhabricatorCalendarEventTransaction::TYPE_START_DATE:
      case PhabricatorCalendarEventTransaction::TYPE_END_DATE:
      case PhabricatorCalendarEventTransaction::TYPE_DESCRIPTION:
      case PhabricatorCalendarEventTransaction::TYPE_CANCEL:
      case PhabricatorCalendarEventTransaction::TYPE_INVITE:
        return $xaction->getNewValue();
      case PhabricatorCalendarEventTransaction::TYPE_STATUS:
        return (int)$xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorCalendarEventTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        return;
      case PhabricatorCalendarEventTransaction::TYPE_START_DATE:
        $object->setDateFrom($xaction->getNewValue());
        return;
      case PhabricatorCalendarEventTransaction::TYPE_END_DATE:
        $object->setDateTo($xaction->getNewValue());
        return;
      case PhabricatorCalendarEventTransaction::TYPE_STATUS:
        $object->setStatus($xaction->getNewValue());
        return;
      case PhabricatorCalendarEventTransaction::TYPE_DESCRIPTION:
        $object->setDescription($xaction->getNewValue());
        return;
      case PhabricatorCalendarEventTransaction::TYPE_CANCEL:
        $object->setIsCancelled((int)$xaction->getNewValue());
        return;
      case PhabricatorCalendarEventTransaction::TYPE_INVITE:
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_EDGE:
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorCalendarEventTransaction::TYPE_NAME:
      case PhabricatorCalendarEventTransaction::TYPE_START_DATE:
      case PhabricatorCalendarEventTransaction::TYPE_END_DATE:
      case PhabricatorCalendarEventTransaction::TYPE_STATUS:
      case PhabricatorCalendarEventTransaction::TYPE_DESCRIPTION:
      case PhabricatorCalendarEventTransaction::TYPE_CANCEL:
        return;
      case PhabricatorCalendarEventTransaction::TYPE_INVITE:
        $map = $xaction->getNewValue();
        $phids = array_keys($map);
        $invitees = array();

        if ($map) {
          $invitees = id(new PhabricatorCalendarEventInviteeQuery())
            ->setViewer($this->getActor())
            ->withEventPHIDs(array($object->getPHID()))
            ->withInviteePHIDs($phids)
            ->execute();
          $invitees = mpull($invitees, null, 'getInviteePHID');
        }

        foreach ($phids as $phid) {
          $invitee = idx($invitees, $phid);
          if (!$invitee) {
            $invitee = id(new PhabricatorCalendarEventInvitee())
              ->setEventPHID($object->getPHID())
              ->setInviteePHID($phid)
              ->setInviterPHID($this->getActingAsPHID());
          }
          $invitee->setStatus($map[$phid])
            ->save();
        }
        return;
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_EDGE:
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    switch ($type) {
      case PhabricatorCalendarEventTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Event name is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;
    }

    return $errors;
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array($object->getUserPHID());
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }
}