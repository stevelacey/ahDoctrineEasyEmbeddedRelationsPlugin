<?php

/**
 * Doctrine form base class that makes it pretty easy to embed one or multiple related forms including creation forms.
 *
 * @package    ahDoctrineEasyEmbeddedRelationsPlugin
 * @subpackage form
 * @author     Daniel Lohse <info@asaphosting.de>
 * @author     Krzysztof Kotowicz <kkotowicz at gmail dot com>
 * @author     Gadfly <gadfly@linux-coders.org>
 * @author     Fabrizio Bottino <fabryb@fabryb.com>
 */
abstract class ahBaseFormDoctrine extends sfFormDoctrine
{
  protected
    $scheduledForDeletion = array(), // related objects scheduled for deletion
    $embeddedRelations = array(),       // so we can check which relations are embedded in this form
    $defaultRelationSettings = array(
        'considerNewFormEmptyFields' => array(),
        'noNewForm' => false,
        'newFormLabel' => null,
        'newFormClass' => null,
        'newFormClassArgs' => array(),
        'formClass' => null,
        'formClassArgs' => array(),
        'displayEmptyRelations' => false,
        'newFormAfterExistingRelations' => false,
        'customEmbeddedFormLabelMethod' => null,
        'formFormatter' => null,
        'multipleNewForms' => false,
        'newFormsInitialCount' => 2,
        'newFormsContainerForm' => null, // pass BaseForm object here or we will create ahNewRelationsContainerForm
        'newRelationButtonLabel' => '+',
        'newRelationAddByCloning' => true,
        'newRelationUseJSFramework' => 'jQuery'
    );

  protected function addDefaultRelationSettings(array $settings)
  {
    return array_merge($this->defaultRelationSettings, $settings);
  }

  public function embedRelations(array $relations)
  {
    $this->embeddedRelations = $relations;
    
    if (false !== ($parentForm = $this->getOption('ah_parent_form', false)))
    {
      $parentForm->addEmbeddedRelation($this->getOption('ah_parent_form_relation'), $relations);
    }

    $this->getEventDispatcher()->connect('form.post_configure', array($this, 'listenToFormPostConfigureEvent'));

    foreach ($relations as $relationName => $relationSettings)
    {
      $relationSettings = $this->addDefaultRelationSettings($relationSettings);

      $relation = $this->getObject()->getTable()->getRelation($relationName);
      if (!$relationSettings['noNewForm'])
      {
        $containerName = 'new_'.$relationName;
        $formLabel = $relationSettings['newFormLabel'];
        if (!$relation->isOneToOne())
        {
          if ($relationSettings['multipleNewForms']) // allow multiple new forms for this relation
          {
            $newFormsCount = $relationSettings['newFormsInitialCount'];

            $subForm = $this->newFormsContainerFormFactory($relationSettings, $containerName);
            for ($i = 0; $i < $newFormsCount; $i++)
            {
              // we need to create new forms with cloned object inside (otherwise only the last new values would be saved)
              $newForm = $this->embeddedFormFactory($relationName, $relationSettings, $relation, $i + 1);
              $subForm->embedForm($i, $newForm);
            }
            $subForm->getWidgetSchema()->setLabel($formLabel);
            $this->embedForm($containerName, $subForm);
          }
          else // just a single new form for this relation
          {
            $newForm = $this->embeddedFormFactory($relationName, $relationSettings, $relation, $formLabel);
            $this->embedForm($containerName, $newForm);
          }
        }
        elseif ($relation->isOneToOne() && !$this->getObject()->relatedExists($relationName))
        {
          $newForm = $this->embeddedFormFactory($relationName, $relationSettings, $relation, $formLabel);
          $this->embedForm($containerName, $newForm);
        }
      }

      $formClass = (null === $relationSettings['formClass']) ? $relation->getClass().'Form' : $relationSettings['formClass'];
      $formArgs = (null === $relationSettings['formClassArgs']) ? array() : $relationSettings['formClassArgs'];
      if ((isset($formArgs[0]) && !array_key_exists('ah_add_delete_checkbox', $formArgs[0])) || !isset($formArgs[0]))
      {
        $formArgs[0]['ah_add_delete_checkbox'] = true;
      }
      
      $parentRelation = $this->getOption('ah_parent_form_relation', array());
      $parentForm = $this->getOption('ah_parent_form', $this);
      array_push($parentRelation, $relationName, 'embeddedRelation');
      
      $formArgs[0]['ah_parent_form'] = $parentForm;
      $formArgs[0]['ah_parent_form_relation'] = $parentRelation;
      //echo print_r(get_class($this->getObject()), true)."\n";
      //echo print_r($parentRelation, true);
      
      if ($relation->isOneToOne())
      {
        $form = new $formClass($this->getObject()->$relationName, $formArgs[0]);
        $this->embedForm($relationName, $form);
        
        /*if (!$this->getObject()->relatedExists($relationName))
        {
          unset($this[$relation->getLocalColumnName()]);
        }*/
      }
      else
      {
        $subForm = new sfForm();

        foreach ($this->getObject()->$relationName as $index => $childObject)
        {
          $form = new $formClass($childObject, $formArgs[0]);

          $subForm->embedForm($index, $form);
          // check if existing embedded relations should have a different label
          if (null === $relationSettings['customEmbeddedFormLabelMethod'] || !method_exists($childObject, $relationSettings['customEmbeddedFormLabelMethod']))
          {
            $subForm->getWidgetSchema()->setLabel($index, (string)$childObject);
          }
          else
          {
            $subForm->getWidgetSchema()->setLabel($index, $childObject->$relationSettings['customEmbeddedFormLabelMethod']());
          }
        }

        $this->embedForm($relationName, $subForm);
      }

      if ($relationSettings['formFormatter']) // switch formatter
      {
        $this->switchFormatter($relationName, $relationSettings['formFormatter']);
      }

      /*
       * Unset the relation form(s) if:
       * (1. One-to-many relation and there are no related objects yet (count of embedded forms is 0) OR
       * 2. One-to-one relation and embedded form is new (no related object yet))
       * AND
       * (3. Option `displayEmptyRelations` was either not set by the user or was set by the user and is false)
       */
      if (
        (
          (!$relation->isOneToOne() && count($this->getEmbeddedForm($relationName)->getEmbeddedForms()) === 0) ||
          ($relation->isOneToOne() && $this->getEmbeddedForm($relationName)->isNew())
        ) &&
        !$relationSettings['displayEmptyRelations']
      )
      {
        unset($this[$relationName]);
      }

      if (
        $relationSettings['newFormAfterExistingRelations'] &&
        isset($this[$relationName]) && isset($this['new_'.$relationName])
      )
      {
        $this->getWidgetSchema()->moveField('new_'.$relationName, sfWidgetFormSchema::AFTER, $relationName);
      }
    }

    $this->getEventDispatcher()->disconnect('form.post_configure', array($this, 'listenToFormPostConfigureEvent'));
  }
  
  public function addEmbeddedRelation($relationName, $relationSettings)
  {
    $orig = $this->embeddedRelations;
    $orig = new Matrix($orig);
    $orig->set(implode('.', $relationName), $relationSettings);
    
    $this->embeddedRelations = $orig->get();
    
    //echo print_r('After:', true)."\n";
    //echo print_r($this->embeddedRelations, true);
  }

  protected function switchFormatter($subFormName, $formatter) {
    $widget = $this[$subFormName]->getWidget()->getWidget();
    $widget->setFormFormatterName($formatter);
    
    // not only do we have to change the name of the formatter, but we also have to re-create the schema decorator
    // as there is no setter for the decorator in sfWidgetFormSchemaDecorator :(
    $this->widgetSchema[$subFormName] = new sfWidgetFormSchemaDecorator($widget, $widget->getFormFormatter()->getDecoratorFormat());
  }

  public function listenToFormPostConfigureEvent(sfEvent $event)
  {
    $form = $event->getSubject();

    if ($form instanceof sfFormDoctrine && $form->getOption('ah_add_delete_checkbox', false) && !$form->isNew())
    {
      $form->setWidget('delete_object', new sfWidgetFormInputCheckbox(array('label' => 'Delete')));
      $form->setValidator('delete_object', new sfValidatorPass(array('required' => false)));

      return $form;
    }
    
    if ($form instanceof sfFormDoctrine && $form->getOption('ah_add_ignore_checkbox', true) && $form->isNew())
    {
      $form->setWidget('ignore_object', new sfWidgetFormInputCheckbox(array('label' => 'Ignore')));
      $form->setValidator('ignore_object', new sfValidatorPass(array('required' => false)));
      $form->setDefault('ignore_object', true);

      return $form;
    }
    
    return false;
  }

  /**
   * Here we just drop the embedded creation forms if no value has been
   * provided for them (this simulates a non-required embedded form),
   * please provide the fields for the related embedded form in the call
   * to $this->embedRelations() so we don't throw validation errors
   * if the user did not want to add a new related object
   *
   * @see sfForm::doBind()
   */
  protected function doBind(array $values)
  {
    //echo print_r($this->embeddedRelations, true);
    foreach ($this->embeddedRelations as $relationName => $relationSettings)
    {
      $values = $this->doBindEmbeddedRelation($values, $relationName, $relationSettings);
    }
    
    parent::doBind($values);
  }
  
  protected function doBindEmbeddedRelation($values, $relationName, $relationSettings, $parentTableClass = null)
  {
    $relationSettings = $this->addDefaultRelationSettings($relationSettings);
    
    if (null === $parentTableClass)
    {
      $parentTableClass = $this->getObject()->getTable();
    }

    if (!$relationSettings['noNewForm'])
    {
      $containerName = 'new_'.$relationName;

      if ($relationSettings['multipleNewForms']) // multiple new forms for this relation
      {
        if (array_key_exists($containerName, $values))
        {
          foreach ($values[$containerName] as $index => $subFormValues)
          {
            if ($this->isNewFormEmpty($subFormValues, $relationSettings))
            {
              unset($values[$containerName][$index], $this->embeddedForms[$containerName][$index]);
              unset($this->validatorSchema[$containerName][$index]);
            }
            else
            {
              // if new forms were inserted client-side, embed them here
              if (!isset($this->embeddedForms[$containerName][$index]))
              {
                // create and embed new form
                $relation = $parentTableClass->getRelation($relationName);
                $addedForm = $this->embeddedFormFactory($relationName, $relationSettings, $relation, ((int) $index) + 1);
                $ef = $this->embeddedForms[$containerName];
                $ef->embedForm($index, $addedForm);
                // ... and reset other stuff (symfony loses all this since container form is already embedded)
                $this->validatorSchema[$containerName] = $ef->getValidatorSchema();
                $this->widgetSchema[$containerName] = new sfWidgetFormSchemaDecorator($ef->getWidgetSchema(), $ef->getWidgetSchema()->getFormFormatter()->getDecoratorFormat());
                $this->setDefault($containerName, $ef->getDefaults());
              }
            }
          }
        }

        $this->validatorSchema[$containerName] = $this->embeddedForms[$containerName]->getValidatorSchema();
        
        // check for new forms that were deleted client-side and never submitted
        if (array_key_exists($containerName, $values))
        {
          foreach (array_keys($this->embeddedForms[$containerName]->embeddedForms) as $index)
          {
            if (!array_key_exists($index, $values[$containerName]))
            {
               unset($this->embeddedForms[$containerName][$index]);
               unset($this->validatorSchema[$containerName][$index]);
            }
          }
        }
        
        if (!array_key_exists($containerName, $values) || count($values[$containerName]) === 0) // all new forms were empty
        {
          unset($values[$containerName], $this->validatorSchema[$containerName]);
        }
      }
      else // just a single new form for this relation
      {
        //echo print_r($containerName, true);
        //echo print_r($values, true);
        if (!array_key_exists($containerName, $values) || $this->isNewFormEmpty($values[$containerName], $relationSettings))
        {
          unset($values[$containerName], $this->validatorSchema[$containerName]);
        }
      }
    }

    if (isset($values[$relationName]))
    {
      $relationID = $parentTableClass->getRelation($relationName)->getTable()->getIdentifier();
      $oneToOneRelationFix = $parentTableClass->getRelation($relationName)->isOneToOne() ? array($values[$relationName]) : $values[$relationName];
      
      foreach ($oneToOneRelationFix as $i => $relationValues)
      {
        if (isset($relationValues['delete_object']))
        {
          if (is_array($relationID))
          {
            foreach($relationID as $c) $this->scheduledForDeletion[$relationName][$i][$c] = $relationValues[$c];
          }
          elseif (isset($relationValues[$relationID]))
          {
            $this->scheduledForDeletion[$relationName][$i][$relationID] = $relationValues[$relationID];
          }
        }
      }
    }
    
    if (isset($relationSettings['embeddedRelation']))
    {
      $newParentTableClass = Doctrine::getTable($parentTableClass->getRelation($relationName)->getClass());
      foreach ($relationSettings['embeddedRelation'] as $nestedEmbeddedRelationName => $nestedEmbeddedRelationSettings)
      {
        //echo print_r('Before: '.$relationName.'/'.$nestedEmbeddedRelationName, true)."\n";
        //echo print_r($values[$relationName], true)."\n\n";
        //echo print_r('Before: '.$relationName.'/'.$nestedEmbeddedRelationName, true)."\n";
        //echo print_r($newParentTableClass->getRelation($nestedEmbeddedRelationName)->isOneToOne(), true);
        if (!$newParentTableClass->getRelation($nestedEmbeddedRelationName)->isOneToOne())
        {
          $tmp = $values[$relationName];
          foreach ($tmp as $index => $nestedRelationValues)
          {
            $values[$relationName][$index] = $this->doBindEmbeddedRelation($nestedRelationValues, $nestedEmbeddedRelationName, $nestedEmbeddedRelationSettings, $newParentTableClass);
          }
        }
        else
        {
          //echo print_r($values[$relationName], true);
          $values[$relationName][0] = $this->doBindEmbeddedRelation($values[$relationName][0], $nestedEmbeddedRelationName, $nestedEmbeddedRelationSettings, $newParentTableClass);
        }
      }
    }
    
    return $values;
  }

  /**
   * Updates object with provided values, dealing with eventual relation deletion
   *
   * @see sfFormDoctrine::doUpdateObject()
   */
  protected function doUpdateObject($values)
  {
    if (count($this->getScheduledForDeletion()) > 0)
    {
      foreach ($this->getScheduledForDeletion() as $relationName => $ids)
      {
        $relation = $this->getObject()->getTable()->getRelation($relationName);
        foreach ($ids as $index => $id)
        {
          if ($relation->isOneToOne())
          {
            unset($values[$relationName]);
          }
          else
          {
            unset($values[$relationName][$index]);
          }

          if (!$relation->isOneToOne())
          {
            unset($this->object[$relationName][$index]);
          }
          else
          {
            $this->object->clearRelated($relationName);
          }
          
          Doctrine::getTable($relation->getClass())->find(array_values($id))->delete();
        }
      }
    }

    parent::doUpdateObject($values);

    // set foreign key here
  }

  public function getScheduledForDeletion()
  {
    return $this->scheduledForDeletion;
  }

  /**
   * Saves embedded form objects.
   * TODO: Check if it's possible to use embedRelations in one form and and also use embedRelations in the embedded form!
   *       This means this would be possible:
   *         1. Edit a user object via the userForm and
   *         2. Embed the groups relation (user-has-many-groups) into the groupsForm and embed that into userForm and
   *         2. Embed the permissions relation (group-has-many-permissions) into the groupsForm and
   *         3. Just for kinks, embed the permissions relation again (user-has-many-permissions) into the userForm
   *
   * @param mixed $con   An optional connection object
   * @param array $forms An array of sfForm instances
   *
   * @see sfFormObject::saveEmbeddedForms()
   */
  public function saveEmbeddedForms($con = null, $forms = null)
  {
    if (null === $con) $con = $this->getConnection();
    if (null === $forms) $forms = $this->getEmbeddedForms();

    foreach ($forms as $form)
    {
      if ($form instanceof sfFormObject)
      {
        /**
         * we know it's a form but we don't know what (embedded) relation it represents;
         * this is necessary because we only care about the relations that we(!) embedded
         * so there isn't anything weird happening
         */
        $relationName = $this->getRelationByEmbeddedFormClass($form);
        
        if ($relationName && isset($this->scheduledForDeletion[$relationName]) && $this->isScheduledForDeletion($form->getObject(), $relationName))
        {
          continue;
        }
        
        $form->getObject()->save($con);
        $form->saveEmbeddedForms($con);
      }
      else
      {
        $this->saveEmbeddedForms($con, $form->getEmbeddedForms());
      }
    }
  }

  /**
   * Get the used relation alias when given an embedded form
   *
   * @param sfForm $form A BaseForm instance
   */
  private function getRelationByEmbeddedFormClass($form)
  {
    foreach ($this->getObject()->getTable()->getRelations() as $relation)
    {
      $class = $relation->getClass();
      if ($form->getObject() instanceof $class)
      {
        return $relation->getAlias();
      }
    }

    return false;
  }

  /**
     * Get the used relation alias when given an object
     *
     * @param $object
     */
    private function getRelationAliasByObject($object)
    {
      foreach ($object->getTable()->getRelations() as $alias => $relation)
      {
        $class = $relation->getClass();
        if ($this->getObject() instanceof $class)
        {
          return $alias;
        }
      }
    }

  /**
   * Checks if given form values for new form are 'empty' (i.e. should the form be discarded)
   * @param array $values
   * @param array $relationSettings settings for the embedded relation
   * @return bool
   */
  protected function isNewFormEmpty(array $values, array $relationSettings)
  {
    if (isset($values['ignore_object']))
    {
      return true;
    }
    
    if (count($relationSettings['considerNewFormEmptyFields']) == 0 || !isset($values)) return false;
    
    $emptyFields = 0;
    foreach ($relationSettings['considerNewFormEmptyFields'] as $field)
    {
      if (is_array($values[$field]))
      {
        if (count($values[$field]) === 0)
        {
          $emptyFields++;
        }
        elseif (array_key_exists('tmp_name', $values[$field]) && $values[$field]['tmp_name'] === '' && $values[$field]['size'] === 0)
        {
          $emptyFields++;
        }
      }
      elseif ('' === trim($values[$field]))
      {
        $emptyFields++;
      }
    }

    if ($emptyFields === count($relationSettings['considerNewFormEmptyFields']))
    {
      return true;
    }

    return false;
  }

  /**
   * Creates and initializes new form object for a given relation.
   * @internal
   * @param string $relationName
   * @param array $relationSettings
   * @param Doctrine_Relation $relation
   * @param string $formLabel
   * @return sfFormDoctrine
   */
  private function embeddedFormFactory($relationName, array $relationSettings, Doctrine_Relation $relation, $formLabel = null)
  {
      $newFormObject = $this->embeddedFormObjectFactory($relationName, $relation);
      $formClass = (null === $relationSettings['newFormClass']) ? $relation->getClass().'Form' : $relationSettings['newFormClass'];
      $formArgs = (null === $relationSettings['newFormClassArgs']) ? array() : $relationSettings['newFormClassArgs'];
      $r = new ReflectionClass($formClass);

      /* @var $newForm sfFormObject */
      $newForm = $r->newInstanceArgs(array_merge(array($newFormObject), $formArgs));
      $newFormIdentifiers = $newForm->getObject()->getTable()->getIdentifierColumnNames();
      foreach ($newFormIdentifiers as $primaryKey)
      {
        unset($newForm[$primaryKey]);
      }
      unset($newForm[$relation->getForeignColumnName()]);
      
      if (null !== $formLabel)
      {
        $newForm->getWidgetSchema()->setLabel($formLabel);
      }
      
      return $newForm;
  }

  /**
   * Returns Doctrine Record object prepared for form given the relation
   * @param  string $relationName
   * @param  Doctrine_Relation $relation
   * @return Doctrine_Record
   */
  private function embeddedFormObjectFactory($relationName, Doctrine_Relation $relation)
  {
    if (!$relation->isOneToOne())
    {
      $newFormObjectClass = $relation->getClass();
      $newFormObject = new $newFormObjectClass();
      $newFormObject[$this->getRelationAliasByObject($newFormObject)] = $this->getObject();
    } else
    {
      $newFormObject = $this->getObject()->$relationName;
    }
    
    return $newFormObject;
  }

  /**
   * Create and initialize form that will embed 'newly created relation' subforms
   * If no object is given in 'newFormsContainerForm' parameter, it will
   * initialize custom form bundled with this plugin
   * @param array $relationSettings
   * @return sfForm (ahNewRelationsContainerForm by default)
   */
  private function newFormsContainerFormFactory(array $relationSettings, $containerName)
  {
    $subForm = $relationSettings['newFormsContainerForm'];

    if (null === $subForm)
    {
      $subForm = new ahNewRelationsContainerForm(null, array(
        'containerName' => $containerName,
        'addByCloning' => $relationSettings['newRelationAddByCloning'],
        'useJSFramework' => $relationSettings['newRelationUseJSFramework'],
        'newRelationButtonLabel' => $relationSettings['newRelationButtonLabel']
      ));
    }

    if ($relationSettings['formFormatter']) {
      $subForm->getWidgetSchema()->setFormFormatterName($relationSettings['formFormatter']);
    }

    unset($subForm[$subForm->getCSRFFieldName()]);
    
    return $subForm;
  }
  
  /**
   * Checks if form is scheduled for deletion
   * @param $formObject
   * @param string $relationName
   * @return bool
   */
  private function isScheduledForDeletion($formObject, $relationName)
  {
    foreach ($this->scheduledForDeletion[$relationName] as $ids)
    {
      $found = array();
      
      foreach ($ids as $k => $v)
      {
        $found[] = ($formObject->get($k) === $v);
      }
      
      $found = array_unique($found);
      if (count($found) === 1 && $found[0]) return $found;
    }
    
    return false;
  }
}
